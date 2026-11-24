<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\Payments\RecurringBillingGateway;

final class UnavailableSubscriptionGateway implements RecurringBillingGateway
{
    public function code(): string
    {
        return 'subscriptions';
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
