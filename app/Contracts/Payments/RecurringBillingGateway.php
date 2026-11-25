<?php

declare(strict_types=1);

namespace App\Contracts\Payments;

/**
 * Extension point for subscription billing. Phase 1 does not bill subscriptions automatically.
 */
interface RecurringBillingGateway
{
    public function code(): string;

    public function isConfigured(): bool;
}
