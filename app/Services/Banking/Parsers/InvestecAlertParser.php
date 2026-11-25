<?php

declare(strict_types=1);

namespace App\Services\Banking\Parsers;

use App\Services\Banking\BankAlertParser;
use App\Services\Banking\ParsesBankText;

final class InvestecAlertParser implements BankAlertParser
{
    use ParsesBankText;

    public function name(): string
    {
        return 'investec';
    }

    public function matches(string $from, string $subject, string $text): bool
    {
        return str_contains(strtolower($from.$subject.$text), 'investec');
    }

    public function parse(string $text): array
    {
        if (preg_match('/\b(Credit|Debit)\s+R\s*([\d,]+(?:\.\d{2})?)/i', $text, $match) !== 1) {
            return [];
        }

        return [$this->row($text, $this->cents($match[2]), strcasecmp($match[1], 'Debit') === 0 ? 'out' : 'in')];
    }
}
