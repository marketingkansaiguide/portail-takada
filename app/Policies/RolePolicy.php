<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /**
     * Modifier la matrice de rôles permettrait autrement à un administrateur
     * de s'accorder de nouveaux droits. Cette capacité reste super-admin.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole(User::ROLE_SUPER_ADMIN) ? true : false;
    }

    public function viewAny(User $user): bool { return false; }
    public function view(User $user, Role $role): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, Role $role): bool { return false; }
    public function delete(User $user, Role $role): bool { return $role->id !== User::ROLE_SUPER_ADMIN && $role->id !== User::ROLE_AGENCY; }
}
