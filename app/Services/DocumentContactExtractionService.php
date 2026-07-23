<?php

namespace App\Services;

use App\Models\IntelligenceContact;
use App\Models\MarketOrganization;
use App\Models\MarketOrganizationActivity;
use App\Models\MarketOrganizationContact;
use App\Models\SourceDocument;
use Illuminate\Support\Str;

class DocumentContactExtractionService
{
    /**
     * @return array{created:int, updated:int, total:int}
     */
    public function extract(SourceDocument $sourceDocument): array
    {
        $sourceDocument->loadMissing('chunks');

        $created = 0;
        $updated = 0;

        foreach ($sourceDocument->chunks as $chunk) {
            $text = $this->cleanText((string) $chunk->chunk_text);
            $emails = $this->emailsFromText($text);

            foreach ($emails as $email) {
                $context = $this->contextAround($text, $email);
                $emailDomain = $this->domainFromEmail($email);
                $externalOrganization = $this->externalOrganizationFromEmailDomain($emailDomain);
                $organization = $externalOrganization['name']
                    ?? $sourceDocument->related_organization_name
                    ?: $this->inferOrganization($context)
                    ?: $sourceDocument->title;
                $relationshipType = $externalOrganization ? 'competitor' : 'account';
                $personName = $this->inferPersonName($context, $email);
                $jobTitle = $this->inferJobTitle($context, $email);

                $contact = IntelligenceContact::updateOrCreate(
                    [
                        'source_fingerprint' => hash('sha256', 'source-document|' . $sourceDocument->id . '|' . strtolower($email)),
                    ],
                    [
                        'source_document_id' => $sourceDocument->id,
                        'country_id' => $sourceDocument->related_country_id,
                        'source_name' => 'Document intake',
                        'source_url' => $sourceDocument->source_url,
                        'document_url' => $sourceDocument->source_url,
                        'document_title' => $sourceDocument->title,
                        'organization' => Str::limit((string) $organization, 250, ''),
                        'person_name' => $personName,
                        'job_title' => $jobTitle,
                        'relationship_type' => $relationshipType,
                        'contact_status' => 'unreviewed',
                        'email' => strtolower($email),
                        'context_excerpt' => Str::limit($context, 1800, ''),
                        'extracted_at' => now(),
                    ],
                );

                $contact->wasRecentlyCreated ? $created++ : $updated++;

                if ($externalOrganization) {
                    $this->promoteCompetitorContact(
                        $sourceDocument,
                        $contact,
                        $externalOrganization['domain'],
                        $externalOrganization['name'],
                        $personName,
                        $jobTitle,
                        $context,
                    );
                }
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $created + $updated,
        ];
    }

    private function promoteCompetitorContact(
        SourceDocument $sourceDocument,
        IntelligenceContact $intelligenceContact,
        string $domain,
        string $organizationName,
        ?string $personName,
        ?string $jobTitle,
        string $context,
    ): void {
        $crawler = $this->competitorEnrichmentCrawler();
        $sourceOrganization = $sourceDocument->related_organization_name ?: $sourceDocument->title;
        $note = 'Classified as competitor/bidder contact because the email domain differs from the source client/government organization. '
            . 'Extracted from tender/source document "' . $sourceDocument->title . '"'
            . ($sourceOrganization ? ' for ' . $sourceOrganization : '')
            . ' (source document #' . $sourceDocument->id . '). Website and address should be enriched by crawler.';

        $organization = MarketOrganization::firstOrNew([
            'source_fingerprint' => hash('sha256', 'document-intake-competitor-domain|' . strtolower($domain)),
        ]);

        if (! $organization->exists) {
            $organization->fill([
                'name' => Str::limit($organizationName, 255, ''),
                'name_normalized' => Str::lower(Str::limit($organizationName, 500, '')),
                'organization_type' => 'private_company',
                'industry' => 'other',
                'organization_subcategory' => 'other',
                'website_url' => 'https://' . $domain,
                'website_domain' => $domain,
                'market_crawler_id' => $crawler?->id,
                'status' => 'active',
                'lead_status' => 'researching',
                'lead_source' => 'document_intake_competitor_extraction',
                'notes' => $this->mergeNote(null, $note),
            ])->save();
        } elseif (! Str::contains((string) $organization->notes, 'source document #' . $sourceDocument->id)) {
            $organization->fill([
                'lead_status' => $organization->lead_status ?: 'researching',
                'lead_source' => $organization->lead_source ?: 'document_intake_competitor_extraction',
                'market_crawler_id' => $organization->market_crawler_id ?: $crawler?->id,
                'notes' => $this->mergeNote((string) $organization->notes, $note),
            ])->save();
        }

        MarketOrganizationContact::updateOrCreate(
            ['source_fingerprint' => hash('sha256', 'document-intake-competitor-contact|' . $sourceDocument->id . '|' . strtolower($intelligenceContact->email))],
            [
                'market_organization_id' => $organization->id,
                'market_crawler_id' => $crawler?->id,
                'contact_type' => 'competitor_contact',
                'person_name' => $personName,
                'job_title' => $jobTitle,
                'email' => strtolower((string) $intelligenceContact->email),
                'notes' => $note,
                'source_url' => route('sls.knowledge.show', $sourceDocument, false),
                'context_excerpt' => Str::limit($context, 1800, ''),
                'verification_status' => 'published_source_unreviewed',
                'extracted_at' => now(),
            ],
        );

        MarketOrganizationActivity::firstOrCreate(
            [
                'market_organization_id' => $organization->id,
                'activity_type' => 'document_intake',
                'subject' => 'Bidder/contact extracted from source document #' . $sourceDocument->id,
            ],
            [
                'body' => $note,
                'activity_at' => now(),
                'logged_by' => 'system',
            ],
        );
    }

    private function competitorEnrichmentCrawler(): ?\App\Models\MarketCrawler
    {
        return \App\Models\MarketCrawler::firstOrCreate(
            ['crawler_key' => 'competitor_bidder_enrichment'],
            [
                'name' => 'Competitor and bidder enrichment crawler',
                'crawler_type' => 'leadership_contact_crawler',
                'description' => 'Enriches competitor and bidder accounts created from tender documents by checking websites, procurement/supplier pages, leadership, HR, IT, news, and public contacts.',
                'is_enabled' => true,
            ],
        );
    }

    private function mergeNote(?string $existing, string $note): string
    {
        $existing = trim((string) $existing);

        if ($existing === '') {
            return $note;
        }

        if (Str::contains($existing, $note)) {
            return $existing;
        }

        return $existing . "\n\n" . $note;
    }

    /**
     * @return array<int, string>
     */
    private function emailsFromText(string $text): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $email) => strtolower(trim($email, " \t\n\r\0\x0B.,;:()[]<>")))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{name:string, domain:string}|null
     */
    private function externalOrganizationFromEmailDomain(?string $domain): ?array
    {
        if (! $domain || $this->isClientOrPublicDomain($domain)) {
            return null;
        }

        return [
            'domain' => $domain,
            'name' => $this->organizationNameFromDomain($domain),
        ];
    }

    private function domainFromEmail(string $email): ?string
    {
        $domain = strtolower(trim((string) Str::of($email)->after('@')));
        $domain = preg_replace('/^www\./', '', $domain) ?? $domain;

        return filter_var('x@' . $domain, FILTER_VALIDATE_EMAIL) ? $domain : null;
    }

    private function isClientOrPublicDomain(string $domain): bool
    {
        $domain = strtolower($domain);

        if (Str::endsWith($domain, ['.gov', '.gov.uk', '.gov.au', '.gov.za', '.pa.gov'])) {
            return true;
        }

        $publicEmailDomains = [
            'gmail.com', 'googlemail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'live.com', 'icloud.com', 'aol.com', 'proton.me', 'protonmail.com',
        ];

        return in_array($domain, $publicEmailDomains, true);
    }

    private function organizationNameFromDomain(string $domain): string
    {
        $known = [
            'kpmg.com' => 'KPMG',
            'telushealth.com' => 'TELUS Health',
            'mfrconsultants.com' => 'MFR Consultants',
            'greencastleconsulting.com' => 'Greencastle Consulting',
            '1alphaconsulting.com' => '1Alpha Consulting',
            'm-inc.com' => 'M Inc.',
            'ene-it-consulting.com' => 'ENE IT Consulting',
            'sagitec.com' => 'Sagitec',
            'tegrit.com' => 'Tegrit',
            'my3tech.com' => 'My3Tech',
            'majesco.com' => 'Majesco',
            'vitechinc.com' => 'Vitech',
            'adept-consulting.com' => 'Adept Consulting',
        ];

        if (isset($known[$domain])) {
            return $known[$domain];
        }

        $root = explode('.', $domain)[0] ?? $domain;
        $root = Str::of($root)
            ->replace(['-', '_'], ' ')
            ->replaceMatches('/\b(llc|ltd|inc|corp|co)\b/i', '')
            ->squish()
            ->title()
            ->toString();

        return $root !== '' ? $root : $domain;
    }

    private function contextAround(string $text, string $needle): string
    {
        $position = stripos($text, $needle);

        if ($position === false) {
            return Str::squish(Str::limit($text, 700, ''));
        }

        $start = max(0, $position - 450);
        $snippet = substr($text, $start, 950);

        return Str::squish($snippet);
    }

    private function inferPersonName(string $context, string $email): ?string
    {
        $window = Str::of($context)->replaceMatches('/\s+/', ' ')->toString();

        if ($name = $this->inferNameBeforeEmail($window, $email)) {
            return $name;
        }

        if ($name = $this->inferNameFromEmail($email)) {
            return $name;
        }

        $genericPrefixes = [
            'admin', 'admissions', 'contact', 'director', 'enquiries', 'enquiry', 'finance',
            'help', 'hr', 'info', 'mail', 'office', 'procurement', 'registrar', 'sales',
            'secretary', 'support', 'tender', 'tenders', 'vc',
        ];

        $prefix = strtolower((string) Str::of($email)->before('@'));

        if (in_array($prefix, $genericPrefixes, true) || str_contains($prefix, '.')) {
            return null;
        }

        if (preg_match('/\b(?:Mr\.?|Mrs\.?|Ms\.?|Dr\.?|Prof\.?)\s+([A-Z][A-Za-z\'\.-]+(?:\s+[A-Z][A-Za-z\'\.-]+){1,3})\b/', $window, $match)) {
            return Str::limit(trim($match[0]), 120, '');
        }

        if (preg_match('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3})\s*,\s*(?:Chief|Director|Head|Manager|Registrar|Secretary|Coordinator|Officer|President|Vice|CEO|CFO|CTO)/', $window, $match)) {
            return Str::limit(trim($match[1]), 120, '');
        }

        return null;
    }

    private function inferNameBeforeEmail(string $context, string $email): ?string
    {
        $position = stripos($context, $email);

        if ($position === false) {
            return null;
        }

        $before = trim(substr($context, max(0, $position - 150), min(150, $position)));

        if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $before, $priorEmails, PREG_OFFSET_CAPTURE)) {
            $lastEmail = end($priorEmails[0]);
            if (is_array($lastEmail)) {
                $before = substr($before, ((int) $lastEmail[1]) + strlen((string) $lastEmail[0]));
            }
        }

        $before = preg_replace('/[^\pL\pM\pN\s,\.\'\-&()]+/u', ' ', $before) ?? $before;
        $before = Str::squish($before);

        $noise = '(?:SERS|BDISBO|KPMG|GSD|GSDTM|External|Inc|LLC|Ltd|Limited|Company|Consulting|Health|Technologies|Solutions|Group|Partners)';

        if (preg_match('/\b([A-Z][\pL\pM\'\.-]{1,40}(?:\s+[A-Z][\pL\pM\'\.-]{1,40}){1,3})\s*(?:,\s*' . $noise . '|\(\s*' . $noise . '\s*\))\s*$/u', $before, $match)) {
            $candidate = $this->cleanNameCandidate($match[1]);

            if ($candidate !== null) {
                return $candidate;
            }
        }

        if (preg_match('/\b([A-Z][\pL\pM\'\.-]{1,40}),\s*([A-Z][\pL\pM\'\.-]{1,40}(?:\s+[A-Z]\.?)?)\s*(?:' . $noise . '\b\s*)?$/u', $before, $match)) {
            return Str::limit(Str::squish(trim($match[2] . ' ' . $match[1])), 120, '');
        }

        if (preg_match('/\b([A-Z][\pL\pM\'\.-]{1,40}(?:\s+[A-Z][\pL\pM\'\.-]{1,40}){1,3})(?:,\s*' . $noise . '|\s*\(\s*' . $noise . '\s*\))?\s*$/u', $before, $match)) {
            $candidate = $this->cleanNameCandidate($match[1]);

            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function inferNameFromEmail(string $email): ?string
    {
        $prefix = strtolower((string) Str::of($email)->before('@'));
        $genericPrefixes = [
            'admin', 'admissions', 'contact', 'director', 'enquiries', 'enquiry', 'finance',
            'help', 'hr', 'info', 'mail', 'office', 'procurement', 'registrar', 'sales',
            'secretary', 'support', 'tender', 'tenders', 'vc',
        ];

        if (in_array($prefix, $genericPrefixes, true)) {
            return null;
        }

        if (preg_match('/^[a-z][a-z\'-]{1,30}(?:\.[a-z][a-z\'-]{1,30}){1,3}$/', $prefix)) {
            return Str::of($prefix)
                ->replace('.', ' ')
                ->title()
                ->toString();
        }

        return null;
    }

    private function cleanNameCandidate(string $candidate): ?string
    {
        $candidate = preg_replace('/\b(?:SERS|BDISBO|KPMG|GSD|GSDTM|External)\b/u', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/^(?:com|org|net|edu|gov|co|ac|pa|za|uk|ng|bd)\s+/iu', '', $candidate) ?? $candidate;
        $candidate = Str::squish(trim($candidate, " \t\n\r\0\x0B,.-"));

        if ($candidate === '' || str_word_count($candidate) < 2) {
            return null;
        }

        if (preg_match('/\b(?:Date|Subject|Number|Due|Addendum|Attendance|Supplier|Email|Title|Name)\b/i', $candidate)) {
            return null;
        }

        return Str::limit($candidate, 120, '');
    }

    private function inferJobTitle(string $context, string $email): ?string
    {
        $prefix = strtolower((string) Str::of($email)->before('@'));
        $genericTitleMap = [
            'admissions' => 'Admissions',
            'director' => 'Director',
            'finance' => 'Finance',
            'hr' => 'Human Resources',
            'procurement' => 'Procurement',
            'registrar' => 'Registrar',
            'secretary' => 'Secretary',
            'tender' => 'Tender contact',
            'tenders' => 'Tender contact',
            'vc' => 'Vice Chancellor',
        ];

        foreach ($genericTitleMap as $fragment => $title) {
            if ($prefix === $fragment || str_contains($prefix, $fragment)) {
                return $title;
            }
        }

        $titles = [
            'Chief Executive Officer', 'Chief Financial Officer', 'Chief Technology Officer',
            'Managing Director', 'Executive Director', 'Director', 'Head of Procurement',
            'Procurement Officer', 'Registrar', 'Vice Chancellor', 'President',
            'Project Coordinator', 'Project Manager', 'Human Resources Manager',
            'Finance Manager', 'Secretary',
        ];

        foreach ($titles as $title) {
            if (stripos($context, $title) !== false) {
                return $title;
            }
        }

        return null;
    }

    private function inferOrganization(string $context): ?string
    {
        if (preg_match('/\b([A-Z][A-Za-z&,\.\' -]+(?:University|Ministry|Fund|Agency|Authority|Bank|Corporation|Company|Limited|Ltd\.?|Inc\.?|Board|Commission)[A-Za-z&,\.\' -]*)\b/', $context, $match)) {
            return Str::squish($match[1]);
        }

        return null;
    }

    private function cleanText(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
        $text = str_replace(["\u{2013}", "\u{2014}"], '-', $text);
        $text = str_replace("\u{FFFD}", ' ', $text);

        return Str::squish($text);
    }
}
