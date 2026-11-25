<?php

declare(strict_types=1);

namespace App\Contracts\Payments;

/**
 * Extension point for PayFast and Yoco. Phase 1 captures those methods
 * manually and does not call either provider.
 */
interface OnlinePaymentGateway
{
    public function code(): string;

    public function isConfigured(): bool;
}
