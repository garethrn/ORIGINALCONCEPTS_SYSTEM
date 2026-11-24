<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canBill() || $user->role === Role::Client;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->role === Role::Client) {
            return $user->client_id !== null
                && (int) $user->client_id === (int) $invoice->client_id
                && ! $invoice->isDraft();
        }

        return $user->role->canBill();
    }

    public function create(User $user): bool
    {
        return $user->role->canBill();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && $user->role->canBill();
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return ! $invoice->isDraft() && $user->role->canBill();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && $user->role->canBill();
    }
}
