<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->string('id')->primary(); // 'admin', 'agent', 'agency'
            $table->string('display_name');
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        // Insertion des profils par défaut pour éviter toute régression immédiate
        DB::table('roles')->insert([
            [
                'id' => 'admin',
                'display_name' => 'Administrateur Interne',
                'permissions' => json_encode([
                    'folder.viewAny', 'folder.view', 'folder.create', 'folder.update', 'folder.delete',
                    'product.viewAny', 'product.view', 'product.create', 'product.update', 'product.delete',
                    'agency.viewAny', 'agency.view', 'create.agency', 'agency.update', 'agency.delete',
                    'user.viewAny', 'user.view', 'user.create', 'user.update', 'user.delete',
                    'setting.viewAny', 'setting.view', 'setting.create', 'setting.update', 'setting.delete',
                    'client_group.viewAny', 'client_group.view', 'client_group.create', 'client_group.update', 'client_group.delete',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'agent',
                'display_name' => 'Agent de Réservation',
                'permissions' => json_encode([
                    'folder.viewAny', 'folder.view', 'folder.create', 'folder.update',
                    'product.viewAny', 'product.view',
                    'agency.viewAny', 'agency.view',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'agency',
                'display_name' => 'Client Externe (Agence)',
                'permissions' => json_encode([]), // Géré par le Front-Office plus tard
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};