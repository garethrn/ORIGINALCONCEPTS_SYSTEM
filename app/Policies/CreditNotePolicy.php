<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\CreditNote;
use App\Models\User;

class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canIssueCredit() || $user->role === Role::Client;
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        if ($user->role === Role::Client) {
            return (int) $user->client_id === (int) $creditNote->client_id && ! $creditNote->isDraft();
        }

        return $user->role->canIssueCredit() || $user->role === Role::Manager;
    }

    public function create(User $user): bool
    {
        return $user->role->canIssueCredit();
    }

    public function issue(User $user, CreditNote $creditNote): bool
    {
        return $creditNote->isDraft() && $user->role->canIssueCredit();
    }

    public function send(User $user, CreditNote $creditNote): bool
    {
        return ! $creditNote->isDraft() && $user->role->canIssueCredit();
    }
}
