<?php

namespace App\Policies;

use App\Models\BusStation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BusStationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BusStation $busStation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BusStation $busStation): bool
    {
        return true;
    }

    public function delete(User $user, BusStation $busStation): bool
    {
        return true;
    }
}