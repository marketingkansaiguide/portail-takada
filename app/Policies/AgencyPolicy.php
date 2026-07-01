<?php

namespace App\Policies;

use App\Models\Agency;
use App\Models\User;

class AgencyPolicy
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
        return $user->hasPermission('agency.viewAny');
    }

    public function view(User $user, Agency $agency): bool
    {
        return $user->hasPermission('agency.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('agency.create');
    }

    public function update(User $user, Agency $agency): bool
    {
        return $user->hasPermission('agency.update');
    }

    public function delete(User $user, Agency $agency): bool
    {
        return $user->hasPermission('agency.delete');
    }

    public function restore(User $user, Agency $agency): bool
    {
        return $user->hasPermission('agency.restore');
    }

    public function forceDelete(User $user, Agency $agency): bool
    {
        return false;
    }
}