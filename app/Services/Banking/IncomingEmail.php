<?php

declare(strict_types=1);

namespace App\Services\Banking;

final class IncomingEmail
{
    /**
     * @param  list<array{name: string, mime: string, content: string}>  $attachments
     */
    public function __construct(
        public readonly string $from,
        public readonly string $subject,
        public readonly string $messageId,
        public readonly string $body,
        public readonly array $attachments,
        public readonly string $raw,
    ) {}

    public static function fromRaw(string $raw): self
    {
        $raw = str_replace("\r\n", "\n", $raw);
        [$header, $body] = array_pad(explode("\n\n", $raw, 2), 2, '');
        $headers = self::headers($header);
        $from = $headers['from'] ?? '';
        $subject = self::decode($headers['subject'] ?? '');
        $messageId = trim($headers['message-id'] ?? '', '<> ');
        $contentType = $headers['content-type'] ?? 'text/plain';
        $attachments = [];
        $text = $body;
        if (preg_match('/boundary="?([^";]+)"?/i', $contentType, $match) === 1) {
            [$text, $attachments] = self::parts($body, $match[1]);
        } elseif (str_contains(strtolower($contentType), 'base64')) {
            $text = base64_decode(preg_replace('/\s+/', '', $body) ?: '') ?: $body;
        }

        return new self(self::address($from), $subject, $messageId, trim($text), $attachments, $raw);
    }

    /**
     * @return array<string, string>
     */
    private static function headers(string $header): array
    {
        $unfolded = preg_replace("/\n[ \t]+/", ' ', $header) ?? $header;
        $headers = [];
        foreach (explode("\n", $unfolded) as $line) {
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $match) === 1) {
                $headers[strtolower($match[1])] = trim($match[2]);
            }
        }

        return $headers;
    }

    /**
     * @return array{0: string, 1: list<array{name: string, mime: string, content: string}>}
     */
    private static function parts(string $body, string $boundary): array
    {
        $chunks = explode('--'.$boundary, $body);
        $text = '';
        $attachments = [];
        foreach ($chunks as $chunk) {
            $chunk = ltrim($chunk, "\n");
            if ($chunk === '' || str_starts_with($chunk, '--')) {
                continue;
            }
            [$partHeader, $partBody] = array_pad(explode("\n\n", $chunk, 2), 2, '');
            $headers = self::headers($partHeader);
            $mime = strtolower(trim(strtok($headers['content-type'] ?? 'text/plain', ';')));
            $content = str_contains(strtolower($headers['content-transfer-encoding'] ?? ''), 'base64')
                ? (base64_decode(preg_replace('/\s+/', '', $partBody) ?: '') ?: '')
                : trim($partBody);
            $name = 'attachment';
            if (preg_match('/filename="?([^";]+)"?/i', ($headers['content-disposition'] ?? '').($headers['content-type'] ?? ''), $match) === 1) {
                $name = $match[1];
            }
            if (str_starts_with($mime, 'text/plain') && $text === '') {
                $text = $content;
                continue;
            }
            if ($mime === 'application/pdf') {
                $content = self::pdfText($content);
                $mime = 'text/plain';
            }
            $attachments[] = ['name' => $name, 'mime' => $mime, 'content' => $content];
        }

        return [$text, $attachments];
    }

    private static function pdfText(string $pdf): string
    {
        $text = '';
        if (preg_match_all('/\((?:\\\\.|[^\\\\)]){2,}\)/', $pdf, $matches) === 1 || $matches[0] !== []) {
            foreach ($matches[0] as $literal) {
                $text .= ' '.stripcslashes(substr($literal, 1, -1));
            }
        }

        return trim($text);
    }

    private static function address(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $match) === 1) {
            return strtolower(trim($match[1]));
        }

        return strtolower(trim($from));
    }

    private static function decode(string $value): string
    {
        return preg_replace_callback('/=\?([^?]+)\?([bq])\?([^?]+)\?=/i', function (array $match): string {
            $decoded = strtolower($match[2]) === 'b' ? base64_decode($match[3]) : quoted_printable_decode(str_replace('_', ' ', $match[3]));

            return $decoded === false ? $match[0] : $decoded;
        }, $value) ?? $value;
    }
}
