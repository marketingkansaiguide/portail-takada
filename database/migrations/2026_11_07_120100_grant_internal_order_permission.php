<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Lot 3b — droit de commander les prestations préparées (ajout seulement). */
return new class extends Migration
{
    private const ROLES = ['admin', 'agent', 'commercial_fit', 'commercial_groupe', 'billetterie'];

    public function up(): void
    {
        foreach (self::ROLES as $roleId) {
            $role = DB::table('roles')->where('id', $roleId)->first();
            if (! $role) {
                continue;
            }
            $current = json_decode($role->permissions ?: '[]', true) ?: [];
            DB::table('roles')->where('id', $roleId)->update(['permissions' => json_encode(array_values(array_unique([...$current, 'folder.internal.order'])))]);
        }
    }

    public function down(): void
    {
        foreach (self::ROLES as $roleId) {
            $role = DB::table('roles')->where('id', $roleId)->first();
            if (! $role) {
                continue;
            }
            $current = json_decode($role->permissions ?: '[]', true) ?: [];
            DB::table('roles')->where('id', $roleId)->update(['permissions' => json_encode(array_values(array_diff($current, ['folder.internal.order'])))]);
        }
    }
};
