<?php

namespace App\Policies;

use App\Models\ClientGroup;
use App\Models\User;

class ClientGroupPolicy
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
        return $user->hasPermission('client_group.viewAny');
    }

    public function view(User $user, ClientGroup $clientGroup): bool
    {
        return $user->hasPermission('client_group.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('client_group.create');
    }

    public function update(User $user, ClientGroup $clientGroup): bool
    {
        return $user->hasPermission('client_group.update');
    }

    public function delete(User $user, ClientGroup $clientGroup): bool
    {
        return $user->hasPermission('client_group.delete');
    }

    public function restore(User $user, ClientGroup $clientGroup): bool
    {
        return $user->hasPermission('client_group.restore');
    }

    public function forceDelete(User $user, ClientGroup $clientGroup): bool
    {
        return false;
    }
}