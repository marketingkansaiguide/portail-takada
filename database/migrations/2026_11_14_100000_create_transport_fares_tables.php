<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grille tarifaire des billets de train / bus : prix connus par trajet, train, classe et saison,
 * affichés à l'agence à la place de « Sur devis » quand le trajet est dans la grille.
 * Additive et réversible : aucune donnée existante n'est modifiée (sauf l'ajout du droit aux rôles).
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'transport_fare.viewAny', 'transport_fare.view', 'transport_fare.create',
        'transport_fare.update', 'transport_fare.delete',
    ];

    public function up(): void
    {
        Schema::create('transport_fares', function (Blueprint $t) {
            $t->id();
            // Noms tels qu'ils sont choisis par l'agence (name_en des gares) ; *_key = forme normalisée pour la recherche.
            $t->string('departure_station', 150);
            $t->string('arrival_station', 150);
            $t->string('departure_key', 150)->index();
            $t->string('arrival_key', 150)->index();
            $t->boolean('both_directions')->default(true);
            // Famille de train (« Nozomi », « Hida »…) ; vide = tous les trains du trajet.
            $t->string('train_name', 100)->nullable();
            // Classe = option du produit billet (non réservée, réservée, Green, Gran Class).
            $t->foreignId('product_option_id')->constrained('product_options')->cascadeOnDelete();
            $t->string('pricing_mode', 10)->default('seasonal'); // seasonal | flat
            $t->unsignedInteger('price_low')->nullable();
            $t->unsignedInteger('price_normal')->nullable();
            $t->unsignedInteger('price_high')->nullable();
            $t->unsignedInteger('price_peak')->nullable();
            $t->unsignedInteger('price_flat')->nullable();
            $t->unsignedInteger('child_price')->nullable();
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('source', 100)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('transport_fare_seasons', function (Blueprint $t) {
            $t->id();
            $t->string('company', 50)->nullable(); // informatif (Toutes JR, JR East…)
            $t->string('season', 10); // low | high | peak (le reste de l'année = normal)
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('notes')->nullable();
            $t->timestamps();
            $t->index(['starts_on', 'ends_on']);
        });

        // Droit accordé aux rôles qui gèrent déjà le catalogue produits.
        foreach (DB::table('roles')->get() as $role) {
            $current = json_decode($role->permissions ?: '[]', true) ?: [];
            if (! in_array('product.update', $current, true)) {
                continue;
            }
            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_unique([...$current, ...self::PERMISSIONS]))),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('roles')->get() as $role) {
            $current = json_decode($role->permissions ?: '[]', true) ?: [];
            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_diff($current, self::PERMISSIONS))),
            ]);
        }
        Schema::dropIfExists('transport_fare_seasons');
        Schema::dropIfExists('transport_fares');
    }
};
