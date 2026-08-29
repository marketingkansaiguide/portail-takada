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
        return $user->hasPermission('bus_station.viewAny');
    }

    public function view(User $user, BusStation $busStation): bool
    {
        return $user->hasPermission('bus_station.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('bus_station.create');
    }

    public function update(User $user, BusStation $busStation): bool
    {
        return $user->hasPermission('bus_station.update');
    }

    public function delete(User $user, BusStation $busStation): bool
    {
        return $user->hasPermission('bus_station.delete');
    }
}
