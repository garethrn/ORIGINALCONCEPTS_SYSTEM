<?php

declare(strict_types=1);

namespace App\Services\Banking\Parsers;

use App\Services\Banking\BankAlertParser;
use App\Services\Banking\ParsesBankText;

final class StandardBankAlertParser implements BankAlertParser
{
    use ParsesBankText;

    public function name(): string
    {
        return 'standard_bank';
    }

    public function matches(string $from, string $subject, string $text): bool
    {
        $haystack = strtolower($from.' '.$subject.' '.$text);

        return str_contains($haystack, 'standard bank') || str_contains($haystack, 'std bank');
    }

    public function parse(string $text): array
    {
        if (preg_match('/\b(Credit|Debit)\s*:\s*R\s*([\d \x{00A0},]+(?:\.\d{2})?)/iu', $text, $match) !== 1) {
            return [];
        }

        return [$this->row($text, $this->cents($match[2]), strcasecmp($match[1], 'Debit') === 0 ? 'out' : 'in')];
    }
}
