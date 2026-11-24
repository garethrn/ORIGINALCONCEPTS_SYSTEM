<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Product $product): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageProducts();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->role->canManageProducts();
    }
}
