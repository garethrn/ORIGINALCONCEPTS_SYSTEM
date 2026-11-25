<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\QuoteStatus;
use App\Enums\Role;
use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canBill() || $user->role === Role::Client;
    }

    public function view(User $user, Quote $quote): bool
    {
        if ($user->role === Role::Client) {
            return $user->client_id !== null
                && (int) $user->client_id === (int) $quote->client_id
                && ! $quote->isDraft();
        }

        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->role->canBill();
    }

    public function update(User $user, Quote $quote): bool
    {
        return $quote->isDraft() && $user->role->canBill();
    }

    public function issue(User $user, Quote $quote): bool
    {
        return $this->update($user, $quote);
    }

    public function send(User $user, Quote $quote): bool
    {
        return ! $quote->isDraft() && $quote->number && $user->role->canBill();
    }

    public function accept(User $user, Quote $quote): bool
    {
        if ($quote->status !== QuoteStatus::Issued) {
            return false;
        }
        if ($user->role === Role::Client) {
            return (int) $user->client_id === (int) $quote->client_id;
        }

        return $user->role->canBill();
    }

    public function convert(User $user, Quote $quote): bool
    {
        return $user->role->canBill() && in_array($quote->status, [QuoteStatus::Issued, QuoteStatus::Accepted], true);
    }
}
