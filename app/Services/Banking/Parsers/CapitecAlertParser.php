<?php

declare(strict_types=1);

namespace App\Services\Banking\Parsers;

use App\Services\Banking\BankAlertParser;
use App\Services\Banking\ParsesBankText;

final class CapitecAlertParser implements BankAlertParser
{
    use ParsesBankText;

    public function name(): string
    {
        return 'capitec';
    }

    public function matches(string $from, string $subject, string $text): bool
    {
        return str_contains(strtolower($from.$subject.$text), 'capitec');
    }

    public function parse(string $text): array
    {
        if (preg_match('/You\s+(received|paid)\s+R\s*([\d \x{00A0},]+(?:\.\d{2})?)/iu', $text, $match) !== 1) {
            return [];
        }

        return [$this->row($text, $this->cents($match[2]), strtolower($match[1]) === 'paid' ? 'out' : 'in')];
    }
}
