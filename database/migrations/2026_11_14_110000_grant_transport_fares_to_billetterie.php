<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Le rôle billetterie gère aussi la grille tarifaire des billets train (ajout seulement). */
return new class extends Migration
{
    private const PERMISSIONS = [
        'transport_fare.viewAny', 'transport_fare.view', 'transport_fare.create',
        'transport_fare.update', 'transport_fare.delete',
    ];

    public function up(): void
    {
        $role = DB::table('roles')->where('id', 'billetterie')->first();
        if (! $role) {
            return;
        }
        $current = json_decode($role->permissions ?: '[]', true) ?: [];
        DB::table('roles')->where('id', 'billetterie')->update([
            'permissions' => json_encode(array_values(array_unique([...$current, ...self::PERMISSIONS]))),
        ]);
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('id', 'billetterie')->first();
        if (! $role) {
            return;
        }
        $current = json_decode($role->permissions ?: '[]', true) ?: [];
        DB::table('roles')->where('id', 'billetterie')->update([
            'permissions' => json_encode(array_values(array_diff($current, self::PERMISSIONS))),
        ]);
    }
};
