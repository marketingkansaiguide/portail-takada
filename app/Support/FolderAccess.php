<?php

namespace App\Support;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Règle d'accès unique aux dossiers pour les comptes agence.
 *
 * Une agence ne voit QUE les dossiers d'origine B2B dont elle est propriétaire
 * (agency_id). Un dossier interne n'est jamais accessible à une agence, même si
 * elle lui est associée à titre informatif (client_agency_id).
 *
 * Toute surface du panel agence (liste, édition, fichiers, chat, contact, ajout
 * depuis le catalogue) doit passer par cette classe.
 */
final class FolderAccess
{
    /** Restreint une requête aux dossiers visibles par l'agence de l'utilisateur. */
    public static function scopeForAgency(Builder $query, ?User $user): Builder
    {
        return self::scopeForAgencyId($query, $user?->agency_id);
    }

    /** Variante par identifiant d'agence (pages du catalogue agence). */
    public static function scopeForAgencyId(Builder $query, int|string|null $agencyId): Builder
    {
        $table = $query->getModel()->getTable();

        if (! $agencyId) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where("{$table}.origin", Folder::ORIGIN_B2B)
            ->where("{$table}.agency_id", $agencyId);
    }

    public static function agencyMayView(?User $user, ?Folder $folder): bool
    {
        return $user !== null
            && $folder !== null
            && $user->agency_id !== null
            && $folder->origin === Folder::ORIGIN_B2B
            && (int) $folder->agency_id === (int) $user->agency_id;
    }

    public static function agencyMayEdit(?User $user, ?Folder $folder): bool
    {
        return self::agencyMayView($user, $folder);
    }

    /**
     * Droits d'un collaborateur sur les dossiers internes (indépendants du segment).
     * Un compte agence n'a jamais ces droits, même mal configuré.
     */
    public static function staffMay(?User $user, string $action): bool
    {
        if (! $user || $user->hasRole(User::ROLE_AGENCY) || ! $user->isStaff()) {
            return false;
        }

        return $user->hasRole(User::ROLE_SUPER_ADMIN) || $user->hasPermission("folder.internal.{$action}");
    }

    /** Suppression par l'agence : uniquement en brouillon (règle existante). */
    public static function agencyMayDelete(?User $user, ?Folder $folder): bool
    {
        return self::agencyMayView($user, $folder) && $folder->status === 'draft';
    }
}
