<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageUsers();
    }

    public function view(User $user, User $model): bool
    {
        return $user->role->canManageUsers() || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->role->canManageUsers();
    }

    public function update(User $user, User $model): bool
    {
        return $user->role->canManageUsers();
    }
}
