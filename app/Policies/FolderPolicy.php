<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;

class FolderPolicy
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
        return $user->hasPermission('folder.viewAny');
    }

    public function view(User $user, Folder $folder): bool
    {
        return $user->hasPermission('folder.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('folder.create');
    }

    public function update(User $user, Folder $folder): bool
    {
        return $user->hasPermission('folder.update');
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $user->hasPermission('folder.delete');
    }

    public function restore(User $user, Folder $folder): bool
    {
        return $user->hasPermission('folder.restore');
    }

    public function forceDelete(User $user, Folder $folder): bool
    {
        return false;
    }
}