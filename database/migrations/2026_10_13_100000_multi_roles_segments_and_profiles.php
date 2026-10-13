<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'is_staff')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('is_staff')->default(false)->after('permissions');
            });
        }

        // Le super_admin n'a pas forcément de ligne dans roles : on la crée (verrouillée).
        if (! DB::table('roles')->where('id', 'super_admin')->exists()) {
            DB::table('roles')->insert([
                'id' => 'super_admin', 'display_name' => 'Super Administrateur',
                'permissions' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('roles')->whereIn('id', ['super_admin', 'admin', 'agent'])->update(['is_staff' => true]);

        if (! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role_id');
                $table->primary(['user_id', 'role_id']);
            });
        }

        // Reprise de l'existant : chaque compte garde son rôle actuel.
        foreach (DB::table('users')->select('id', 'role')->get() as $u) {
            if ($u->role && DB::table('roles')->where('id', $u->role)->exists()) {
                DB::table('role_user')->insertOrIgnore(['user_id' => $u->id, 'role_id' => $u->role]);
            }
        }

        if (! Schema::hasColumn('folders', 'segment')) {
            Schema::table('folders', function (Blueprint $table) {
                $table->string('segment', 20)->nullable()->index();
            });
        }

        // Les profils existants conservent la vue sur tous les dossiers.
        foreach (['admin', 'agent'] as $id) {
            $this->addPermissions($id, ['folder.segment.all']);
        }

        $crud = fn (string $k): array => array_map(fn ($a) => "$k.$a", ['viewAny', 'view', 'create', 'update', 'delete']);
        $read = fn (string $k): array => ["$k.viewAny", "$k.view"];
        $ops = ['operations.checkins', 'operations.train_tickets', 'operations.iwa_orders', 'operations.ic_cards', 'operations.seven_tickets'];

        $profiles = [
            'marketing_it' => ['Marketing / IT', [
                'dashboard.view', 'operations.statistics',
                ...$crud('product'), ...$crud('category'), ...$read('agency'), ...$read('client_group'),
                ...$crud('setting'), ...$read('user'), 'user.create', 'user.update',
                'seo.dashboard', ...$crud('seo_site'),
            ]],
            'billetterie' => ['Billetterie', [
                'dashboard.view', ...$ops,
                'folder.viewAny', 'folder.view', 'folder.create', 'folder.update',
                'folder.segment.fit', 'folder.segment.groupe', 'folder.segment.billetterie',
                ...$read('product'), ...$read('agency'), ...$read('supplier'), ...$read('hotel'),
                ...$crud('train_station'), ...$crud('bus_station'), ...$crud('item_status'),
            ]],
            'commercial_fit' => ['Commercial FIT', [
                'dashboard.view', 'operations.checkins',
                'folder.viewAny', 'folder.view', 'folder.create', 'folder.update', 'folder.segment.fit',
                ...$read('product'), 'agency.viewAny', 'agency.view', 'agency.create', 'agency.update',
                ...$read('client_group'), ...$read('supplier'), ...$read('hotel'),
                ...$read('train_station'), ...$read('bus_station'),
            ]],
            'commercial_groupe' => ['Commercial Groupe', [
                'dashboard.view', 'operations.checkins',
                'folder.viewAny', 'folder.view', 'folder.create', 'folder.update', 'folder.segment.groupe',
                ...$read('product'), 'agency.viewAny', 'agency.view', 'agency.create', 'agency.update',
                ...$read('client_group'), ...$crud('supplier'), ...$crud('hotel'),
                ...$read('train_station'), ...$read('bus_station'),
            ]],
            'direction' => ['Direction / Finance', [
                'dashboard.view', ...$ops, 'operations.statistics',
                ...$read('folder'), 'folder.segment.all',
                ...$read('product'), ...$read('category'), ...$read('agency'), ...$crud('client_group'),
                ...$read('supplier'), ...$read('hotel'), 'seo.dashboard', ...$read('seo_site'),
            ]],
        ];

        foreach ($profiles as $id => [$name, $perms]) {
            if (DB::table('roles')->where('id', $id)->exists()) {
                continue;
            }
            DB::table('roles')->insert([
                'id' => $id, 'display_name' => $name, 'is_staff' => true,
                'permissions' => json_encode(array_values(array_unique($perms))),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function addPermissions(string $roleId, array $permissions): void
    {
        $role = DB::table('roles')->where('id', $roleId)->first();
        if (! $role) {
            return;
        }
        $current = json_decode($role->permissions ?: '[]', true) ?: [];
        DB::table('roles')->where('id', $roleId)->update([
            'permissions' => json_encode(array_values(array_unique([...$current, ...$permissions]))),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Volontairement non destructif : les profils peuvent avoir été ajustés.
    }
};
