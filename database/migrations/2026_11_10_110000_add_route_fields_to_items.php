<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trajet, heure d'arrivée et date de fin sur les prestations préparées et commandées (copiés à la commande,
 * comme le lieu de rendez-vous), plus un profil de saisie facultatif par produit.
 * Additive et réversible : aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['folder_planned_items', 'folder_items'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('route_from', 150)->nullable();
                $t->string('route_to', 150)->nullable();
                $t->string('end_time', 5)->nullable();
                $t->date('end_date')->nullable();
            });
        }

        Schema::table('products', fn (Blueprint $t) => $t->string('entry_profile', 20)->nullable());
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('entry_profile'));
        foreach (['folder_items', 'folder_planned_items'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['route_from', 'route_to', 'end_time', 'end_date']));
        }
    }
};
