<?php

declare(strict_types=1);

namespace App\Services\Banking;

use App\Services\Banking\Parsers\AbsaAlertParser;
use App\Services\Banking\Parsers\CapitecAlertParser;
use App\Services\Banking\Parsers\FnbAlertParser;
use App\Services\Banking\Parsers\GenericAlertParser;
use App\Services\Banking\Parsers\InvestecAlertParser;
use App\Services\Banking\Parsers\NedbankAlertParser;
use App\Services\Banking\Parsers\StandardBankAlertParser;

final class BankAlertRegistry
{
    /**
     * @return list<BankAlertParser>
     */
    public function parsers(): array
    {
        return [
            new FnbAlertParser,
            new StandardBankAlertParser,
            new AbsaAlertParser,
            new NedbankAlertParser,
            new CapitecAlertParser,
            new InvestecAlertParser,
            new GenericAlertParser,
        ];
    }

    /**
     * @return array{parser: string, rows: list<array{amount_cents: int, posted_on: string, description: string, reference: string, direction: string, last4: ?string}>}
     */
    public function parse(string $from, string $subject, string $text): array
    {
        foreach ($this->parsers() as $parser) {
            if (! $parser->matches($from, $subject, $text)) {
                continue;
            }
            $rows = $parser->parse($text);
            if ($rows !== []) {
                return ['parser' => $parser->name(), 'rows' => $rows];
            }
        }

        return ['parser' => 'none', 'rows' => []];
    }
}
