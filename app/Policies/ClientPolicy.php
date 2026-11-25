<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Client $client): bool
    {
        if ($user->role === Role::Client) {
            return $user->client_id !== null && $user->client_id === $client->id;
        }

        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageClients();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->role->canManageClients();
    }
}
