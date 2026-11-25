<?php

declare(strict_types=1);

namespace App\Services\Banking;

trait ParsesBankText
{
    protected function cents(string $raw): int
    {
        $raw = str_replace([',', ' '], '', trim($raw));
        if (! is_numeric($raw)) {
            return 0;
        }

        return (int) round(((float) $raw) * 100);
    }

    protected function signed(int $cents, string $direction): int
    {
        return $direction === 'out' ? -abs($cents) : abs($cents);
    }

    protected function direction(string $text): string
    {
        return preg_match('/\b(paid|debit|debited|withdrawal|sent)\b/i', $text) === 1
            && preg_match('/\b(received|credit|credited|deposit)\b/i', $text) !== 1
            ? 'out'
            : (preg_match('/\b(paid|debit|debited)\b/i', $text) === 1 ? 'out' : 'in');
    }

    protected function date(string $text): string
    {
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $match) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $match[3], (int) $match[2], (int) $match[1]);
        }
        if (preg_match('/\b(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})\b/i', $text, $match) === 1) {
            return \Illuminate\Support\Carbon::parse($match[0])->toDateString();
        }
        if (preg_match('/\b(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{4})\b/i', $text, $match) === 1) {
            return \Illuminate\Support\Carbon::parse($match[0])->toDateString();
        }

        return today()->toDateString();
    }

    protected function last4(string $text): ?string
    {
        if (preg_match('/(?:\*{2,}|\.{2,}|x{2,}|ending\s+)(\d{4})\b/i', $text, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/\b(?:acc(?:ount)?\.?\s*)?(?:\d+\*+)(\d{4})\b/i', $text, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    protected function reference(string $text): string
    {
        if (preg_match('/\b(INV-\d+)\b/i', $text, $match) === 1) {
            return strtoupper($match[1]);
        }
        if (preg_match('/\b(?:ref(?:erence)?|narrative)\.?\s*[:.]?\s*([A-Z0-9][A-Z0-9 \-]{2,40})/i', $text, $match) === 1) {
            return trim($match[1]);
        }

        return '';
    }

    /**
     * @return array{amount_cents: int, posted_on: string, description: string, reference: string, direction: string, last4: ?string}
     */
    protected function row(string $text, int $cents, string $direction, ?string $last4 = null): array
    {
        $reference = $this->reference($text);

        return [
            'amount_cents' => $this->signed($cents, $direction),
            'posted_on' => $this->date($text),
            'description' => trim(preg_replace('/\s+/', ' ', $text) ?? $text),
            'reference' => $reference,
            'direction' => $direction,
            'last4' => $last4 ?? $this->last4($text),
        ];
    }
}
