<?php

declare(strict_types=1);

namespace App\Services\Banking\Parsers;

use App\Services\Banking\BankAlertParser;
use App\Services\Banking\ParsesBankText;

final class AbsaAlertParser implements BankAlertParser
{
    use ParsesBankText;

    public function name(): string
    {
        return 'absa';
    }

    public function matches(string $from, string $subject, string $text): bool
    {
        return str_contains(strtolower($from.$subject.$text), 'absa');
    }

    public function parse(string $text): array
    {
        if (preg_match('/\b(debited|credited)\b.*?R\s*([\d,]+(?:\.\d{2})?)/i', $text, $match) !== 1
            && preg_match('/R\s*([\d,]+(?:\.\d{2})?)\b.*?\b(debited|credited)\b/i', $text, $match) !== 1) {
            return [];
        }
        $word = strtolower($match[1]);
        $amount = $match[2];
        if (! str_contains($word, 'debit') && ! str_contains($word, 'credit')) {
            $word = strtolower($match[2]);
            $amount = $match[1];
        }

        return [$this->row($text, $this->cents($amount), str_contains($word, 'debit') ? 'out' : 'in')];
    }
}
