<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(User::ROLE_SUPER_ADMIN)) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('product.viewAny');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasPermission('product.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('product.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission('product.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission('product.delete');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->hasPermission('product.restore');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return false;
    }
}