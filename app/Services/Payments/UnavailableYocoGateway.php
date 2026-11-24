<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\Payments\OnlinePaymentGateway;

final class UnavailableYocoGateway implements OnlinePaymentGateway
{
    public function code(): string
    {
        return 'yoco';
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
