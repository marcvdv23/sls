<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use App\Support\EurLexDocumentClassifier;
use App\Support\EurLexTitleCleaner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EurLexWebServiceClient
{
    public function search(
        string $expertQuery,
        int $page,
        int $pageSize,
        string $language = 'en',
        bool $excludeAllConsleg = false,
        bool $limitToLatestConsleg = false,
        ?string $endpointUrl = null,
        ?string $username = null,
        ?string $password = null,
        int $timeoutSeconds = 60,
    ): array {
        $endpointUrl = trim((string) ($endpointUrl ?: env('EURLEX_WEBSERVICE_ENDPOINT', 'https://eur-lex.europa.eu/EURLexWebService')));
        $username = trim((string) ($username ?: env('EURLEX_WEBSERVICE_USERNAME', '')));
        $password = (string) ($password ?: env('EURLEX_WEBSERVICE_PASSWORD', ''));

        if ($endpointUrl === '') {
            throw new RuntimeException('EUR-Lex webservice endpoint is not configured.');
        }

        if ($username === '' || $password === '') {
            throw new RuntimeException('EUR-Lex webservice credentials are not configured. Set EURLEX_WEBSERVICE_USERNAME and EURLEX_WEBSERVICE_PASSWORD.');
        }

        $response = Http::timeout($timeoutSeconds)
            ->withHeaders([
                'Accept' => 'text/xml, multipart/*',
                'Content-Type' => 'application/soap+xml; charset=utf-8',
                'SOAPAction' => 'https://eur-lex.europa.eu/EURLexWebService/doQuery',
            ])
            ->withBody($this->soapEnvelope($expertQuery, $page, $pageSize, $language, $excludeAllConsleg, $limitToLatestConsleg, $username, $password), 'application/soap+xml; charset=utf-8')
            ->post($endpointUrl);

        if (! $response->successful()) {
            $this->throwWebserviceError($response->status(), $response->body());
        }

        return $this->parseSearchResponse($response->body());
    }

    private function soapEnvelope(string $expertQuery, int $page, int $pageSize, string $language, bool $excludeAllConsleg, bool $limitToLatestConsleg, string $username, string $password): string
    {
        return sprintf(
            <<<'XML'
<soap:Envelope xmlns:sear="http://eur-lex.europa.eu/search" xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
  <soap:Header>
    <wsse:Security soap:mustUnderstand="true" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
      <wsse:UsernameToken wsu:Id="UsernameToken-SLS" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
        <wsse:Username>%s</wsse:Username>
        <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">%s</wsse:Password>
      </wsse:UsernameToken>
    </wsse:Security>
  </soap:Header>
  <soap:Body>
    <sear:searchRequest>
      <sear:expertQuery>%s</sear:expertQuery>
      <sear:page>%d</sear:page>
      <sear:pageSize>%d</sear:pageSize>
      <sear:searchLanguage>%s</sear:searchLanguage>
      <sear:excludeAllConsleg>%s</sear:excludeAllConsleg>
      <sear:limitToLatestConsleg>%s</sear:limitToLatestConsleg>
    </sear:searchRequest>
  </soap:Body>
</soap:Envelope>
XML,
            e($username),
            e($password),
            e($expertQuery),
            max(1, $page),
            max(1, $pageSize),
            e($language),
            $excludeAllConsleg ? 'true' : 'false',
            $limitToLatestConsleg ? 'true' : 'false',
        );
    }

    private function parseSearchResponse(string $xml): array
    {
        $document = new DOMDocument();

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (Throwable) {
            $loaded = false;
        }

        if (! $loaded) {
            throw new RuntimeException('EUR-Lex webservice returned invalid XML.');
        }

        $xpath = new DOMXPath($document);

        $fault = $this->firstNodeText($xpath, 'Fault');
        if ($fault !== '') {
            throw new RuntimeException('EUR-Lex webservice fault: ' . Str::limit($fault, 500));
        }

        $results = [];
        foreach ($this->resultNodes($xpath) as $resultNode) {
            if (! $resultNode instanceof DOMElement) {
                continue;
            }

            $resultText = $this->squish($resultNode->textContent);
            $celex = $this->firstValueForNames($xpath, $resultNode, [
                    'DN',
                    'CELEX',
                    'ID_CELEX',
                    'RESOURCE_LEGAL_ID_CELEX',
                    'WORK_ID_DOCUMENT',
                ])
                ?: $this->extractCelex($resultText);

            $rawTitle = $this->firstValueForNames($xpath, $resultNode, [
                    'TI_DISPLAY',
                    'TITLE',
                    'EXPRESSION_TITLE',
                    'EXPRESSION_TITLE_ALTERNATIVE',
                    'WORK_TITLE',
                ])
                ?: $this->firstTitleLikeNodeText($xpath, $resultNode)
                ?: ($celex ? 'EUR-Lex legislation ' . $celex : 'EUR-Lex legislation item');
            $title = $this->cleanTitle($rawTitle);

            $publicationDate = $this->normalDate(
                $this->firstValueForNames($xpath, $resultNode, [
                    'DD',
                    'DATE_DOCUMENT',
                    'WORK_DATE_DOCUMENT',
                    'DATE_PUBLICATION',
                    'MANIFESTATION_OFFICIAL-JOURNAL_PART_PAGE_FIRST',
                ])
                ?: $this->firstDateLikeNodeText($xpath, $resultNode)
            );

            $sourceUrl = $this->firstDocumentLink($xpath, $resultNode, 'HTML')
                ?: ($celex ? $this->eurlexUrl($celex, 'HTML') : '');
            $pdfUrl = $this->firstDocumentLink($xpath, $resultNode, 'PDF')
                ?: ($celex ? $this->eurlexUrl($celex, 'PDF') : null);
            $xmlUrl = $this->firstDocumentLink($xpath, $resultNode, 'XML')
                ?: ($celex ? $this->eurlexUrl($celex, 'XML') : null);

            if ($sourceUrl === '') {
                continue;
            }

            $classification = EurLexDocumentClassifier::classify($celex, trim($rawTitle . ' ' . $title . ' ' . $resultText), 'EUR-Lex', $sourceUrl);

            if ($title === '' || EurLexTitleCleaner::isFallbackTitle($title)) {
                $summaryTitle = $this->cleanTitle($resultText);
                $title = $summaryTitle !== '' ? $summaryTitle : $title;
            }

            if ($title === '') {
                $title = EurLexTitleCleaner::fallbackTitle($classification['legal_document_code'] ?? $celex, $classification['legal_instrument_type'] ?? null);
            }

            $results[] = [
                'title' => $this->squish($title),
                'celex' => $celex ? strtoupper($celex) : null,
                'legal_document_code' => $classification['legal_document_code'],
                'legal_instrument_type' => $classification['legal_instrument_type'],
                'legislation_stage' => $classification['legislation_stage'],
                'source_url' => $sourceUrl,
                'pdf_url' => $pdfUrl,
                'xml_url' => $xmlUrl,
                'source_name' => 'EUR-Lex',
                'publication_date' => $publicationDate,
                'summary' => $resultText,
                'raw_match_text' => trim($title . ' ' . $resultText . ' CELEX ' . $celex),
            ];
        }

        return [
            'page' => (int) ($this->firstNodeText($xpath, 'page') ?: 0),
            'page_size' => count($results),
            'num_hits' => (int) ($this->firstNodeText($xpath, 'numhits') ?: count($results)),
            'total_hits' => (int) ($this->firstNodeText($xpath, 'totalhits') ?: count($results)),
            'items' => $results,
        ];
    }

    private function throwWebserviceError(int $status, string $xml): never
    {
        $fault = $this->faultText($xml);

        if ($fault !== '') {
            throw new RuntimeException('EUR-Lex webservice fault: ' . Str::limit($fault, 1000));
        }

        throw new RuntimeException(
            'EUR-Lex webservice returned HTTP ' . $status . ': ' . Str::limit($this->squish($xml), 1000)
        );
    }

    private function faultText(string $xml): string
    {
        $document = new DOMDocument();

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (Throwable) {
            $loaded = false;
        }

        if (! $loaded) {
            return '';
        }

        $xpath = new DOMXPath($document);
        $fault = $this->firstNodeText($xpath, 'Fault');

        if ($fault === '') {
            return '';
        }

        $details = collect([
            $this->firstNodeText($xpath, 'Text'),
            $this->firstNodeText($xpath, 'faultstring'),
            $this->firstNodeText($xpath, 'message'),
        ])
            ->filter()
            ->unique()
            ->implode(' ');

        return $details !== '' ? $details : $fault;
    }

    private function firstNodeText(DOMXPath $xpath, string $localName, ?DOMElement $context = null): string
    {
        $expression = ($context ? './/' : '//') . '*[local-name()="' . $localName . '"]';
        $nodes = $xpath->query($expression, $context);
        $node = $nodes && $nodes->length > 0 ? $nodes->item(0) : null;

        return $node ? $this->squish($node->textContent) : '';
    }

    private function resultNodes(DOMXPath $xpath): array
    {
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $queries = [
            '//*[translate(local-name(), "' . $lower . '", "' . $upper . '")="RESULT"]',
            '//*[local-name()="NOTICE"]/ancestor::*[translate(local-name(), "' . $lower . '", "' . $upper . '")="RESULT"]',
            '//*[local-name()="NOTICE"]/..',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);

            if ($nodes && $nodes->length > 0) {
                $results = [];
                foreach ($nodes as $node) {
                    if ($node instanceof DOMElement) {
                        $results[] = $node;
                    }
                }

                return $results;
            }
        }

        return [];
    }

    private function firstValueForNames(DOMXPath $xpath, DOMElement $context, array $localNames): string
    {
        foreach ($localNames as $localName) {
            $value = $this->firstValueText($xpath, $localName, $context);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function firstValueText(DOMXPath $xpath, string $localName, DOMElement $context): string
    {
        $expression = './/*[local-name()="' . $localName . '"]';
        $nodes = $xpath->query($expression, $context);

        foreach ($nodes ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $valueNode = $xpath->query('.//*[local-name()="VALUE"]', $node)?->item(0);
            $text = $this->squish($valueNode?->textContent ?: $node->textContent);

            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function firstTitleLikeNodeText(DOMXPath $xpath, DOMElement $context): string
    {
        $nodes = $xpath->query('.//*[contains(translate(local-name(), "abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ"), "TITLE")]', $context);

        foreach ($nodes ?: [] as $node) {
            $valueNode = $node instanceof DOMElement ? $xpath->query('.//*[local-name()="VALUE"]', $node)?->item(0) : null;
            $text = $this->squish($valueNode?->textContent ?: $node->textContent);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function firstDateLikeNodeText(DOMXPath $xpath, DOMElement $context): string
    {
        $nodes = $xpath->query('.//*[contains(translate(local-name(), "abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ"), "DATE")]', $context);

        foreach ($nodes ?: [] as $node) {
            $valueNode = $node instanceof DOMElement ? $xpath->query('.//*[local-name()="VALUE"]', $node)?->item(0) : null;
            $date = $this->normalDate($valueNode?->textContent ?: $node->textContent);
            if ($date !== null) {
                return $date;
            }
        }

        return '';
    }

    private function firstDocumentLink(DOMXPath $xpath, DOMElement $context, string $format): ?string
    {
        $format = strtoupper($format);
        $nodes = $xpath->query('.//*[contains(translate(local-name(), "abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ"), "LINK") or contains(translate(local-name(), "abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ"), "CONTENT") or contains(translate(local-name(), "abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ"), "ITEM")]', $context);

        foreach ($nodes ?: [] as $node) {
            $text = $this->squish($node->textContent);

            if ($text !== '' && Str::startsWith($text, 'http') && Str::contains(Str::upper($text), '/' . $format . '/')) {
                return $text;
            }

            if ($node instanceof DOMElement) {
                foreach (['href', 'url'] as $attribute) {
                    $value = $node->getAttribute($attribute);
                    if ($value !== '' && Str::startsWith($value, 'http') && Str::contains(Str::upper($value), '/' . $format . '/')) {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    private function extractCelex(string $value): ?string
    {
        return preg_match('/\b([0-9][0-9]{4}[A-Z]{1,3}[0-9A-Z]{3,}(?:R(?:\([0-9A-Z]+\))?|\([0-9A-Z]+\))?)\b/i', $value, $matches) === 1
            ? strtoupper($matches[1])
            : null;
    }

    private function cleanTitle(string $title): string
    {
        return EurLexTitleCleaner::clean($title);
    }

    private function normalDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/\b([0-9]{4})-([0-9]{2})-([0-9]{2})\b/', $value, $matches) === 1) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }

        if (preg_match('/\b([0-9]{2})\/([0-9]{2})\/([0-9]{4})\b/', $value, $matches) === 1) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        return null;
    }

    private function eurlexUrl(string $celex, string $format): string
    {
        return 'https://eur-lex.europa.eu/legal-content/EN/TXT/' . $format . '/?uri=CELEX:' . rawurlencode($celex);
    }

    private function squish(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
