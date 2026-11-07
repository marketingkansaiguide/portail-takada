<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use App\Support\FolderAccess;

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
        // Autorisé pour les admins OU les utilisateurs d'agence
        return $user->hasRole(User::ROLE_AGENCY) || $user->hasPermission('folder.viewAny');
    }

    public function view(User $user, Folder $folder): bool
    {
        if ($user->hasRole(User::ROLE_AGENCY)) {
            return FolderAccess::agencyMayView($user, $folder);
        }

        if ($folder->isInternal()) {
            return FolderAccess::staffMay($user, 'view');
        }

        return $user->hasPermission('folder.view');
    }

    public function create(User $user): bool
    {
        // 💡 AUTORISE LA CRÉATION pour tous les comptes rattachés à une agence
        return $user->hasRole(User::ROLE_AGENCY) || $user->hasPermission('folder.create');
    }

    /** Création d'un dossier interne (formulaire commercial distinct). */
    public function createInternal(User $user): bool
    {
        return FolderAccess::staffMay($user, 'create');
    }

    public function update(User $user, Folder $folder): bool
    {
        if ($user->hasRole(User::ROLE_AGENCY)) {
            return FolderAccess::agencyMayEdit($user, $folder);
        }

        if ($folder->isInternal()) {
            return FolderAccess::staffMay($user, 'update');
        }

        return $user->hasPermission('folder.update');
    }

    public function delete(User $user, Folder $folder): bool
    {
        // 💡 AUTORISE LA SUPPRESSION par l'agence UNIQUEMENT si le dossier est en BROUILLON
        if ($user->hasRole(User::ROLE_AGENCY)) {
            return FolderAccess::agencyMayDelete($user, $folder);
        }

        if ($folder->isInternal()) {
            return FolderAccess::staffMay($user, 'delete');
        }

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
