<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 2 — Origine des dossiers.
 *
 * - origin : 'b2b' (dossier d'agence, visible par l'agence propriétaire) ou
 *   'internal' (dossier interne Takada, jamais visible par une agence).
 * - client_agency_id : agence associée à titre purement informatif (statistiques),
 *   sans aucun effet sur les accès.
 *
 * Tous les dossiers existants sont classés 'b2b' : leur visibilité ne dépend que
 * d'agency_id, qui n'est pas modifié. La colonne devient ensuite NOT NULL sans
 * valeur par défaut, pour qu'aucune insertion ne puisse oublier l'origine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->string('origin', 16)->nullable()->after('reference');
            $table->foreignId('client_agency_id')->nullable()->after('agency_id')
                ->constrained('agencies')->nullOnDelete();
            $table->index('origin');
        });

        DB::table('folders')->whereNull('origin')->update(['origin' => 'b2b']);

        Schema::table('folders', function (Blueprint $table) {
            $table->string('origin', 16)->nullable(false)->change();
            // Un dossier interne n'a pas d'agence propriétaire. L'obligation d'une
            // agence pour les dossiers B2B est conservée par la contrainte ci-dessous.
            $table->unsignedBigInteger('agency_id')->nullable()->change();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE folders ADD CONSTRAINT folders_origin_valid CHECK (origin IN ('b2b', 'internal'))");
            DB::statement("ALTER TABLE folders ADD CONSTRAINT folders_b2b_has_agency CHECK (origin <> 'b2b' OR agency_id IS NOT NULL)");
            // MySQL interdit main_seller_id dans un CHECK (clé étrangère ON DELETE SET NULL) :
            // l'absence de vendeur agence sur un dossier interne est garantie par le modèle Folder.
            DB::statement("ALTER TABLE folders ADD CONSTRAINT folders_internal_has_no_owner_agency CHECK (origin <> 'internal' OR agency_id IS NULL)");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE folders DROP CHECK folders_internal_has_no_owner_agency');
            DB::statement('ALTER TABLE folders DROP CHECK folders_b2b_has_agency');
            DB::statement('ALTER TABLE folders DROP CHECK folders_origin_valid');
        }

        // Retour arrière impossible tant que des dossiers internes existent :
        // on refuse plutôt que de les supprimer ou de leur inventer une agence.
        if (Schema::hasColumn('folders', 'origin') && DB::table('folders')->where('origin', 'internal')->exists()) {
            throw new RuntimeException('Des dossiers internes existent : restaurer la sauvegarde au lieu de revenir en arrière.');
        }

        Schema::table('folders', function (Blueprint $table) {
            $table->unsignedBigInteger('agency_id')->nullable(false)->change();
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_agency_id');
            $table->dropIndex(['origin']);
            $table->dropColumn('origin');
        });
    }
};
