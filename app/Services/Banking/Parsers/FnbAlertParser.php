<?php

declare(strict_types=1);

namespace App\Services\Banking\Parsers;

use App\Services\Banking\BankAlertParser;
use App\Services\Banking\ParsesBankText;

final class FnbAlertParser implements BankAlertParser
{
    use ParsesBankText;

    public function name(): string
    {
        return 'fnb';
    }

    public function matches(string $from, string $subject, string $text): bool
    {
        return str_contains(strtolower($from.$subject.$text), 'fnb');
    }

    public function parse(string $text): array
    {
        if (preg_match('/R\s*([\d,]+(?:\.\d{2})?)\s+(received|paid)\b/i', $text, $match) !== 1) {
            return [];
        }

        return [$this->row($text, $this->cents($match[1]), strtolower($match[2]) === 'paid' ? 'out' : 'in')];
    }
}
