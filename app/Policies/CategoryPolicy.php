<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
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
        return $user->hasPermission('category.viewAny');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->hasPermission('category.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('category.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermission('category.update');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasPermission('category.delete');
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->hasPermission('category.restore');
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return false;
    }
}