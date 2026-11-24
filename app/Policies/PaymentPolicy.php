<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canTakePayment();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->role->canTakePayment();
    }

    public function create(User $user): bool
    {
        return $user->role->canTakePayment();
    }
}
