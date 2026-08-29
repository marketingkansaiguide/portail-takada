<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsByRole = [
            'admin' => [
                'agency.create',
                'category.viewAny', 'category.view', 'category.create', 'category.update', 'category.delete',
                'supplier.viewAny', 'supplier.view', 'supplier.create', 'supplier.update', 'supplier.delete',
                'hotel.viewAny', 'hotel.view', 'hotel.create', 'hotel.update', 'hotel.delete',
                'item_status.viewAny', 'item_status.view', 'item_status.create', 'item_status.update', 'item_status.delete',
                'train_station.viewAny', 'train_station.view', 'train_station.create', 'train_station.update', 'train_station.delete',
                'bus_station.viewAny', 'bus_station.view', 'bus_station.create', 'bus_station.update', 'bus_station.delete',
                'dashboard.view',
                'operations.checkins', 'operations.train_tickets', 'operations.iwa_orders',
                'operations.ic_cards', 'operations.seven_tickets', 'operations.statistics',
            ],
            // L'agent de réservation conserve ses outils opérationnels, sans
            // accès aux statistiques financières ni aux référentiels sensibles.
            'agent' => [
                'dashboard.view',
                'operations.checkins', 'operations.train_tickets', 'operations.iwa_orders',
                'operations.ic_cards', 'operations.seven_tickets',
            ],
        ];

        foreach ($permissionsByRole as $roleId => $permissions) {
            $role = DB::table('roles')->where('id', $roleId)->first();

            if (! $role) {
                continue;
            }

            $current = json_decode($role->permissions ?: '[]', true);
            $current = is_array($current) ? $current : [];

            DB::table('roles')
                ->where('id', $roleId)
                ->update([
                    'permissions' => json_encode(array_values(array_unique([...$current, ...$permissions]))),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Les permissions sont configurables par l'administrateur : ne pas
        // retirer automatiquement des droits éventuellement ajustés ensuite.
    }
};
