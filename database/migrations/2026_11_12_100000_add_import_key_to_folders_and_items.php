<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repère d'import (reprise du récap billetterie) : rend l'import idempotent (jamais deux fois le même dossier)
 * et permet un retour arrière ciblé (supprimer uniquement ce qui porte un import_key donné).
 * Additive et réversible : aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $t) {
            $t->string('import_key', 80)->nullable()->unique();
        });
        Schema::table('folder_items', function (Blueprint $t) {
            $t->string('import_ref', 80)->nullable()->index();
        });
        Schema::table('agencies', function (Blueprint $t) {
            $t->string('import_key', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn('import_key'));
        Schema::table('folder_items', function (Blueprint $t) {
            $t->dropIndex(['import_ref']);
            $t->dropColumn('import_ref');
        });
        Schema::table('folders', function (Blueprint $t) {
            $t->dropUnique(['import_key']);
            $t->dropColumn('import_key');
        });
    }
};
