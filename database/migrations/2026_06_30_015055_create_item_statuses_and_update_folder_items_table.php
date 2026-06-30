<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Création de la table des statuts configurables
        Schema::create('item_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nom du statut (ex: "Voucher Émis")
            $table->string('color')->default('gray'); // Couleur Filament (success, warning, danger, info, gray)
            $table->timestamps();
        });

        // 2. Ajout de la clé étrangère sur les lignes de prestations
        Schema::table('folder_items', function (Blueprint $table) {
            $table->foreignId('item_status_id')
                ->nullable()
                ->after('product_option_id')
                ->constrained('item_statuses')
                ->nullOnDelete(); // Si on supprime un statut, la prestation repasse à null sans être supprimée
        });
    }

    public function down(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            $table->dropForeign(['item_status_id']);
            $table->dropColumn('item_status_id');
        });
        Schema::dropIfExists('item_statuses');
    }
};