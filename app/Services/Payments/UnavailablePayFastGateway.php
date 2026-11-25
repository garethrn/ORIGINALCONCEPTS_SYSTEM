<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\Payments\OnlinePaymentGateway;

final class UnavailablePayFastGateway implements OnlinePaymentGateway
{
    public function code(): string
    {
        return 'payfast';
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
