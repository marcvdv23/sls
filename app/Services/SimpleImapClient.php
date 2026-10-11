<?php

namespace App\Services;

use RuntimeException;

class SimpleImapClient
{
    /** @var resource|null */
    private $socket = null;

    private int $tagCounter = 0;

    public function connect(string $host, int $port = 993, int $timeout = 30): void
    {
        $this->socket = @fsockopen('ssl://' . $host, $port, $errno, $errstr, $timeout);

        if (! $this->socket) {
            throw new RuntimeException("Could not connect to IMAP server {$host}:{$port}: {$errstr} ({$errno})");
        }

        stream_set_timeout($this->socket, $timeout);

        $greeting = fgets($this->socket);
        if ($greeting === false || ! str_contains($greeting, '* OK')) {
            throw new RuntimeException('Unexpected IMAP greeting: ' . trim((string) $greeting));
        }
    }

    public function login(string $username, string $password): array
    {
        return $this->command('LOGIN ' . $this->quote($username) . ' ' . $this->quote($password));
    }

    public function selectMailbox(string $mailbox = 'INBOX'): array
    {
        return $this->command('SELECT ' . $this->quote($mailbox));
    }

    /**
     * @return array<int, int>
     */
    public function searchUids(string $criteria = 'UNSEEN'): array
    {
        $response = $this->command('UID SEARCH ' . $criteria);
        $uids = [];

        foreach ($response['lines'] as $line) {
            if (preg_match('/^\* SEARCH\s*(.*)$/i', trim($line), $match)) {
                $uids = collect(preg_split('/\s+/', trim($match[1])))
                    ->filter(fn (string $uid) => ctype_digit($uid))
                    ->map(fn (string $uid) => (int) $uid)
                    ->values()
                    ->all();
            }
        }

        return $uids;
    }

    public function fetchMessageByUid(int $uid): ?string
    {
        $response = $this->command('UID FETCH ' . $uid . ' (BODY.PEEK[])');

        return $response['literals'][0] ?? null;
    }

    public function markSeen(int $uid): void
    {
        $this->command('UID STORE ' . $uid . ' +FLAGS.SILENT (\\Seen)');
    }

    public function logout(): void
    {
        if (! $this->socket) {
            return;
        }

        try {
            $this->command('LOGOUT');
        } finally {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * @return array{tag:string, lines:array<int, string>, literals:array<int, string>}
     */
    public function command(string $command): array
    {
        if (! $this->socket) {
            throw new RuntimeException('IMAP socket is not connected.');
        }

        $tag = 'A' . str_pad((string) ++$this->tagCounter, 4, '0', STR_PAD_LEFT);
        fwrite($this->socket, $tag . ' ' . $command . "\r\n");

        $lines = [];
        $literals = [];

        while (($line = fgets($this->socket)) !== false) {
            $lines[] = $line;

            if (preg_match('/\{(\d+)\}\r?\n$/', $line, $match)) {
                $length = (int) $match[1];
                $literal = '';

                while (strlen($literal) < $length && ! feof($this->socket)) {
                    $chunk = fread($this->socket, $length - strlen($literal));
                    if ($chunk === false) {
                        break;
                    }
                    $literal .= $chunk;
                }

                $literals[] = $literal;
            }

            if (str_starts_with($line, $tag . ' ')) {
                if (! preg_match('/^' . preg_quote($tag, '/') . '\s+OK\b/i', $line)) {
                    throw new RuntimeException('IMAP command failed: ' . trim($line));
                }

                return [
                    'tag' => $tag,
                    'lines' => $lines,
                    'literals' => $literals,
                ];
            }
        }

        throw new RuntimeException('IMAP connection closed before tagged response.');
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"\r\n") . '"';
    }
}
