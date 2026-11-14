<?php

namespace App\Policies;

use App\Models\TransportFareSeason;
use App\Models\User;

/** Le calendrier des saisons suit les droits de la grille tarifaire. */
class TransportFareSeasonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('transport_fare.viewAny');
    }

    public function view(User $user, TransportFareSeason $season): bool
    {
        return $user->hasPermission('transport_fare.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('transport_fare.create');
    }

    public function update(User $user, TransportFareSeason $season): bool
    {
        return $user->hasPermission('transport_fare.update');
    }

    public function delete(User $user, TransportFareSeason $season): bool
    {
        return $user->hasPermission('transport_fare.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('transport_fare.delete');
    }
}
