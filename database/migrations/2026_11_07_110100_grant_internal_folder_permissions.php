<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot 3a — Droits sur les dossiers internes.
 * Ajout seulement (aucune permission retirée). Tous les commerciaux voient et
 * modifient tous les dossiers internes, quel que soit leur segment.
 */
return new class extends Migration
{
    private const GRANTS = [
        'admin' => ['folder.internal.viewAny', 'folder.internal.view', 'folder.internal.create', 'folder.internal.update', 'folder.internal.delete'],
        'agent' => ['folder.internal.viewAny', 'folder.internal.view', 'folder.internal.create', 'folder.internal.update'],
        'commercial_fit' => ['folder.internal.viewAny', 'folder.internal.view', 'folder.internal.create', 'folder.internal.update'],
        'commercial_groupe' => ['folder.internal.viewAny', 'folder.internal.view', 'folder.internal.create', 'folder.internal.update'],
        'billetterie' => ['folder.internal.viewAny', 'folder.internal.view', 'folder.internal.create', 'folder.internal.update'],
        'direction' => ['folder.internal.viewAny', 'folder.internal.view'],
    ];

    public function up(): void
    {
        foreach (self::GRANTS as $roleId => $grants) {
            $role = DB::table('roles')->where('id', $roleId)->first();
            if (! $role) {
                continue;
            }
            $current = json_decode($role->permissions ?: '[]', true) ?: [];
            DB::table('roles')->where('id', $roleId)->update([
                'permissions' => json_encode(array_values(array_unique(array_merge($current, $grants)))),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::GRANTS as $roleId => $grants) {
            $role = DB::table('roles')->where('id', $roleId)->first();
            if (! $role) {
                continue;
            }
            $current = json_decode($role->permissions ?: '[]', true) ?: [];
            DB::table('roles')->where('id', $roleId)->update([
                'permissions' => json_encode(array_values(array_diff($current, $grants))),
            ]);
        }
    }
};
