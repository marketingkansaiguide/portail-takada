<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La colonne existe déjà en BDD, on ne fait rien pour valider la migration
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            if (Schema::hasColumn('folders', 'documents')) {
                $table->dropColumn('documents');
            }
        });
    }
};