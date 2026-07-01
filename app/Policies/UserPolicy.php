<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
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
        return $user->hasPermission('user.viewAny');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('user.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('user.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission('user.update');
    }

    public function delete(User $user, User $model): bool
    {
        // Règle de garde essentielle : On ne s'auto-supprime pas
        if ($user->id === $model->id) {
            return false;
        }
        return $user->hasPermission('user.delete');
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasPermission('user.restore');
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}