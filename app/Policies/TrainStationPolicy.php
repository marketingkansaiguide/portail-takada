<?php

namespace App\Policies;

use App\Models\TrainStation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainStationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('train_station.viewAny');
    }

    public function view(User $user, TrainStation $trainStation): bool
    {
        return $user->hasPermission('train_station.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('train_station.create');
    }

    public function update(User $user, TrainStation $trainStation): bool
    {
        return $user->hasPermission('train_station.update');
    }

    public function delete(User $user, TrainStation $trainStation): bool
    {
        return $user->hasPermission('train_station.delete');
    }
}
