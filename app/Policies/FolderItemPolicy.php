<?php

namespace App\Policies;

use App\Models\FolderItem;
use App\Models\User;

class FolderItemPolicy
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
        return $user->hasRole(User::ROLE_AGENCY) || $user->hasPermission('folder.viewAny');
    }

    public function view(User $user, FolderItem $folderItem): bool
    {
        if ($user->hasRole(User::ROLE_AGENCY)) {
            return $folderItem->folder
                && (int) $folderItem->folder->agency_id === (int) $user->agency_id;
        }

        return $user->hasPermission('folder.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_AGENCY) || $user->hasPermission('folder.update');
    }

    public function update(User $user, FolderItem $folderItem): bool
    {
        if ($user->hasRole(User::ROLE_AGENCY)) {
            return $folderItem->folder
                && (int) $folderItem->folder->agency_id === (int) $user->agency_id;
        }

        return $user->hasPermission('folder.update');
    }

    public function delete(User $user, FolderItem $folderItem): bool
    {
        if ($user->hasRole(User::ROLE_AGENCY)) {
            return $folderItem->folder
                && (int) $folderItem->folder->agency_id === (int) $user->agency_id
                && $folderItem->folder->status === 'draft';
        }

        return $user->hasPermission('folder.delete');
    }

    public function restore(User $user, FolderItem $folderItem): bool
    {
        return false;
    }

    public function forceDelete(User $user, FolderItem $folderItem): bool
    {
        return false;
    }
}
