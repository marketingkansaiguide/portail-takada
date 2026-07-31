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
        // Autorisé pour les admins OU les utilisateurs d'agence
        return $user->agency_id !== null || $user->hasPermission('folder.viewAny');
    }

    public function view(User $user, Folder $folder): bool
    {
        // Un vendeur peut voir le dossier s'il appartient à son agence
        if ($user->agency_id !== null && $folder->agency_id === $user->agency_id) {
            return true;
        }

        return $user->hasPermission('folder.view');
    }

    public function create(User $user): bool
    {
        // 💡 AUTORISE LA CRÉATION pour tous les comptes rattachés à une agence
        return $user->agency_id !== null || $user->hasPermission('folder.create');
    }

    public function update(User $user, Folder $folder): bool
    {
        if ($user->agency_id !== null && $folder->agency_id === $user->agency_id) {
            return true;
        }

        return $user->hasPermission('folder.update');
    }

    public function delete(User $user, Folder $folder): bool
    {
        // 💡 AUTORISE LA SUPPRESSION par l'agence UNIQUEMENT si le dossier est en BROUILLON
        if ($user->agency_id !== null && $folder->agency_id === $user->agency_id) {
            return $folder->status === 'draft';
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