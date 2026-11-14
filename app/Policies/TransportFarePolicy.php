<?php

namespace App\Policies;

use App\Models\TransportFare;
use App\Models\User;

class TransportFarePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('transport_fare.viewAny');
    }

    public function view(User $user, TransportFare $fare): bool
    {
        return $user->hasPermission('transport_fare.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('transport_fare.create');
    }

    public function update(User $user, TransportFare $fare): bool
    {
        return $user->hasPermission('transport_fare.update');
    }

    public function delete(User $user, TransportFare $fare): bool
    {
        return $user->hasPermission('transport_fare.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('transport_fare.delete');
    }
}
