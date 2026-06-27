<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // On stocke la CONFIGURATION des champs directement dans la fiche produit
            $table->json('custom_field_definitions')->nullable();
            
            // Nettoyage de l'ancienne colonne de l'étape précédente
            if (Schema::hasColumn('products', 'custom_values')) {
                $table->dropColumn('custom_values');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('custom_field_definitions');
        });
    }
};