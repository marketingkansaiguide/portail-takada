<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Espace commercial dédié : les profils commerciaux n'entrent plus dans
 * l'administration (/admin) mais dans /commercial. Tous les autres profils
 * gardent leur accès actuel (valeur par défaut : oui).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('admin_panel')->default(true)->after('is_staff');
        });

        DB::table('roles')->whereIn('id', ['commercial_fit', 'commercial_groupe'])->update(['admin_panel' => false]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('admin_panel');
        });
    }
};
