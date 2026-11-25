<?php

declare(strict_types=1);

namespace App\Services\Banking\Parsers;

use App\Services\Banking\BankAlertParser;
use App\Services\Banking\ParsesBankText;

final class GenericAlertParser implements BankAlertParser
{
    use ParsesBankText;

    public function name(): string
    {
        return 'generic';
    }

    public function matches(string $from, string $subject, string $text): bool
    {
        return preg_match('/R\s*[\d, ]+\.\d{2}/', $text) === 1;
    }

    public function parse(string $text): array
    {
        if (preg_match('/R\s*([\d, ]+\.\d{2})/', $text, $match) !== 1) {
            return [];
        }

        return [$this->row($text, $this->cents($match[1]), $this->direction($text))];
    }
}
