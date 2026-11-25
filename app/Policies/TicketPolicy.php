<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->role === Role::Client) {
            return $user->client_id !== null && (int) $user->client_id === (int) $ticket->client_id;
        }

        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        if ($user->role === Role::Client) {
            return $user->client_id !== null;
        }

        return $user->role->canManageTickets() || $user->role === Role::Finance;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && $user->role->canManageTickets();
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function bill(User $user, Ticket $ticket): bool
    {
        return $user->role->canBill() && $this->view($user, $ticket);
    }
}
