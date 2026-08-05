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
        return true;
    }

    public function view(User $user, TrainStation $trainStation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TrainStation $trainStation): bool
    {
        return true;
    }

    public function delete(User $user, TrainStation $trainStation): bool
    {
        return true;
    }
}