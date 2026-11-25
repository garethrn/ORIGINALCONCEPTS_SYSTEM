<?php

declare(strict_types=1);

namespace App\Services\Banking;

interface BankAlertParser
{
    public function name(): string;

    public function matches(string $from, string $subject, string $text): bool;

    /**
     * @return list<array{amount_cents: int, posted_on: string, description: string, reference: string, direction: string, last4: ?string}>
     */
    public function parse(string $text): array;
}
