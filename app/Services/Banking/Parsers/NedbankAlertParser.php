<?php

declare(strict_types=1);

namespace App\Services\Banking\Parsers;

use App\Services\Banking\BankAlertParser;
use App\Services\Banking\ParsesBankText;

final class NedbankAlertParser implements BankAlertParser
{
    use ParsesBankText;

    public function name(): string
    {
        return 'nedbank';
    }

    public function matches(string $from, string $subject, string $text): bool
    {
        return str_contains(strtolower($from.$subject.$text), 'nedbank');
    }

    public function parse(string $text): array
    {
        if (preg_match('/\b(deposit|payment)\b of R\s*([\d,]+(?:\.\d{2})?)/i', $text, $match) !== 1) {
            return [];
        }
        $direction = strtolower($match[1]) === 'payment' && preg_match('/\breceived\b/i', $text) !== 1 ? 'out' : 'in';
        if (preg_match('/\breceived\b/i', $text) === 1) {
            $direction = 'in';
        }

        return [$this->row($text, $this->cents($match[2]), $direction)];
    }
}
