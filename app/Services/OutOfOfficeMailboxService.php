<?php

namespace App\Services;

use App\Models\OutOfOfficeReferral;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OutOfOfficeMailboxService
{
    public function testConnection(): array
    {
        $client = $this->connect();

        try {
            $client->selectMailbox($this->mailboxFolder());

            return [
                'ok' => true,
                'mailbox' => $this->mailboxUsername(),
                'folder' => $this->mailboxFolder(),
            ];
        } finally {
            $client->logout();
        }
    }

    /**
     * @return array{mailbox:string, checked:int, oof_detected:int, created:int, updated:int, skipped:int, errors:array<int, string>}
     */
    public function process(int $limit = 25, bool $all = false, bool $dryRun = false, bool $markSeen = false): array
    {
        $client = $this->connect();
        $checked = 0;
        $detected = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        try {
            $client->selectMailbox($this->mailboxFolder());

            $uids = $client->searchUids($all ? 'ALL' : 'UNSEEN');
            $uids = array_slice(array_reverse($uids), 0, max(1, $limit));

            foreach ($uids as $uid) {
                $checked++;

                try {
                    $raw = $client->fetchMessageByUid($uid);
                    if (! $raw) {
                        $skipped++;
                        continue;
                    }

                    $parsed = $this->parseMessage($raw);
                    if (! $this->looksLikeOutOfOffice($parsed['subject'], $parsed['body'])) {
                        $skipped++;
                        continue;
                    }

                    $detected++;
                    $referral = $this->extractReferral($parsed, $uid);

                    if (! $dryRun) {
                        $record = OutOfOfficeReferral::query()->updateOrCreate(
                            ['source_fingerprint' => $referral['source_fingerprint']],
                            $referral
                        );

                        $record->wasRecentlyCreated ? $created++ : $updated++;

                        if ($markSeen) {
                            $client->markSeen($uid);
                        }
                    }
                } catch (\Throwable $exception) {
                    $errors[] = 'UID ' . $uid . ': ' . $exception->getMessage();
                }
            }
        } finally {
            $client->logout();
        }

        return [
            'mailbox' => $this->mailboxUsername(),
            'checked' => $checked,
            'oof_detected' => $detected,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function connect(): SimpleImapClient
    {
        $username = $this->mailboxUsername();
        $password = $this->mailboxPassword();

        if ($username === '' || $password === '') {
            throw new \RuntimeException('Missing OOF mailbox credentials. Set OOF_GMAIL_USERNAME and OOF_GMAIL_APP_PASSWORD in .env.');
        }

        $client = new SimpleImapClient();
        $client->connect((string) env('OOF_GMAIL_HOST', 'imap.gmail.com'), (int) env('OOF_GMAIL_PORT', 993));
        $client->login($username, $password);

        return $client;
    }

    private function mailboxUsername(): string
    {
        return trim((string) env('OOF_GMAIL_USERNAME', env('OOO_GMAIL_USERNAME', '')));
    }

    private function mailboxPassword(): string
    {
        return str_replace(' ', '', trim((string) env('OOF_GMAIL_APP_PASSWORD', env('OOO_GMAIL_APP_PASSWORD', ''))));
    }

    private function mailboxFolder(): string
    {
        return trim((string) env('OOF_GMAIL_FOLDER', 'INBOX')) ?: 'INBOX';
    }

    /**
     * @return array{headers:array<string, string>, subject:string, from_email:?string, from_name:?string, message_id:?string, date:?Carbon, body:string}
     */
    private function parseMessage(string $raw): array
    {
        [$rawHeaders, $rawBody] = preg_split("/\r?\n\r?\n/", $raw, 2) + ['', ''];
        $headers = $this->parseHeaders((string) $rawHeaders);
        $body = $this->decodeBody((string) $rawBody, $headers['content-transfer-encoding'] ?? '');
        $body = $this->plainTextFromBody($body);
        [$fromName, $fromEmail] = $this->parseAddress($headers['from'] ?? '');

        return [
            'headers' => $headers,
            'subject' => $this->decodeHeader($headers['subject'] ?? ''),
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'message_id' => $headers['message-id'] ?? null,
            'date' => filled($headers['date'] ?? null) ? rescue(fn () => Carbon::parse($headers['date']), null, false) : null,
            'body' => $body,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $rawHeaders) ?? $rawHeaders;
        $headers = [];

        foreach (preg_split("/\r?\n/", $unfolded) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $headers[Str::lower(trim($key))] = trim($value);
        }

        return $headers;
    }

    private function decodeHeader(string $value): string
    {
        if (function_exists('mb_decode_mimeheader')) {
            return trim(mb_decode_mimeheader($value));
        }

        return trim($value);
    }

    private function decodeBody(string $body, string $encoding): string
    {
        $encoding = Str::lower(trim($encoding));

        return match ($encoding) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body) ?? $body, true),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function plainTextFromBody(string $body): string
    {
        if (str_contains($body, '<html') || str_contains($body, '<body') || str_contains($body, '<div')) {
            $body = preg_replace('/<br\s*\/?>/i', "\n", $body) ?? $body;
            $body = preg_replace('/<\/p>/i', "\n", $body) ?? $body;
            $body = strip_tags($body);
        }

        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n{3,}/", "\n\n", $body) ?? $body) ?? $body);
    }

    /**
     * @return array{0:?string, 1:?string}
     */
    private function parseAddress(string $address): array
    {
        $address = $this->decodeHeader($address);

        if (preg_match('/^(.*?)<([^>]+)>$/', $address, $match)) {
            return [trim($match[1], " \t\"'"), Str::lower(trim($match[2]))];
        }

        if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return [null, Str::lower($address)];
        }

        return [$address ?: null, null];
    }

    private function looksLikeOutOfOffice(string $subject, string $body): bool
    {
        $text = Str::lower($subject . "\n" . $body);

        return Str::contains($text, [
            'out of office',
            'automatic reply',
            'auto reply',
            'autoreply',
            'auto-reply',
            'i am away',
            'currently away',
            'currently on leave',
            'annual leave',
            'on vacation',
            'out of the office',
        ]);
    }

    /**
     * @param array{subject:string, from_email:?string, from_name:?string, message_id:?string, date:?Carbon, body:string} $message
     * @return array<string, mixed>
     */
    private function extractReferral(array $message, int $uid): array
    {
        $emails = $this->emailsFromText($message['body']);
        $emails = array_values(array_filter($emails, function (string $email) use ($message) {
            return $email !== Str::lower((string) $message['from_email'])
                && $email !== Str::lower($this->mailboxUsername());
        }));

        $referredEmail = $emails[0] ?? null;
        $context = $referredEmail ? $this->contextAround($message['body'], $referredEmail) : $this->firstLines($message['body']);
        $referredName = $referredEmail ? $this->nameNearEmail($context, $referredEmail) : null;

        return [
            'mailbox' => $this->mailboxUsername(),
            'message_uid' => (string) $uid,
            'message_id' => $message['message_id'],
            'message_date' => $message['date'],
            'subject' => Str::limit($message['subject'], 250, ''),
            'from_name' => $message['from_name'],
            'from_email' => $message['from_email'],
            'referred_name' => $referredName,
            'referred_email' => $referredEmail,
            'referred_job_title' => null,
            'referred_organization' => null,
            'context_excerpt' => Str::limit($context, 2000, ''),
            'raw_excerpt' => Str::limit($this->firstLines($message['body']), 4000, ''),
            'status' => $referredEmail ? 'needs_review' : 'no_referral_email_found',
            'source_fingerprint' => hash('sha256', implode('|', [
                'oof',
                $this->mailboxUsername(),
                $message['message_id'] ?: $uid,
            ])),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function emailsFromText(string $text): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $email) => Str::lower(trim($email, " \t\r\n.,;:<>[]()")))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function contextAround(string $body, string $needle): string
    {
        $position = stripos($body, $needle);
        if ($position === false) {
            return $this->firstLines($body);
        }

        $start = max(0, $position - 500);

        return trim(substr($body, $start, 1200));
    }

    private function nameNearEmail(string $context, string $email): ?string
    {
        $quotedEmail = preg_quote($email, '/');
        $patterns = [
            '/(?:contact|reach|email|write to|please contact)\s+([A-Z][A-Za-z .\'-]{2,80})\s+(?:at|on|via)\s+' . $quotedEmail . '/i',
            '/([A-Z][A-Za-z .\'-]{2,80})\s*<\s*' . $quotedEmail . '\s*>/i',
            '/([A-Z][A-Za-z .\'-]{2,80})\s+(?:\(|\[)?' . $quotedEmail . '/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $context, $match)) {
                return trim($match[1], " \t\r\n.,;:-");
            }
        }

        return null;
    }

    private function firstLines(string $body): string
    {
        return trim(implode("\n", array_slice(preg_split("/\r?\n/", $body) ?: [], 0, 25)));
    }
}
