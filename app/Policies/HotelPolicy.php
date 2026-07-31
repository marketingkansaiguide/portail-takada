<?php

namespace App\Policies;

use App\Models\Hotel;
use App\Models\User;

class HotelPolicy
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
        return $user->hasPermission('hotel.viewAny') || $user->agency_id !== null;
    }

    public function view(User $user, Hotel $hotel): bool
    {
        return $user->hasPermission('hotel.view') || $user->agency_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hotel.create');
    }

    public function update(User $user, Hotel $hotel): bool
    {
        return $user->hasPermission('hotel.update');
    }

    public function delete(User $user, Hotel $hotel): bool
    {
        return $user->hasPermission('hotel.delete');
    }

    public function restore(User $user, Hotel $hotel): bool
    {
        return $user->hasPermission('hotel.restore');
    }

    public function forceDelete(User $user, Hotel $hotel): bool
    {
        return false;
    }
}