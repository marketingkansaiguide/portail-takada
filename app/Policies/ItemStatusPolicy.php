<?php

namespace App\Policies;

use App\Models\ItemStatus;
use App\Models\User;

class ItemStatusPolicy
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
        return $user->hasPermission('item_status.viewAny');
    }

    public function view(User $user, ItemStatus $itemStatus): bool
    {
        return $user->hasPermission('item_status.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('item_status.create');
    }

    public function update(User $user, ItemStatus $itemStatus): bool
    {
        return $user->hasPermission('item_status.update');
    }

    public function delete(User $user, ItemStatus $itemStatus): bool
    {
        return $user->hasPermission('item_status.delete');
    }

    public function restore(User $user, ItemStatus $itemStatus): bool
    {
        return $user->hasPermission('item_status.restore');
    }

    public function forceDelete(User $user, ItemStatus $itemStatus): bool
    {
        return false;
    }
}