<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 3a — Socle des dossiers internes.
 *
 * Toutes les colonnes sont facultatives : aucune incidence sur les dossiers B2B.
 * lead_traveler_name / start_date / end_date deviennent facultatifs en base pour
 * permettre la création rapide d'un dossier interne ; les formulaires B2B
 * continuent de les exiger (validation inchangée).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->string('kind', 20)->nullable()->after('origin');            // full_trip | services_only
            $table->string('ops_status', 30)->nullable()->after('status');      // cycle commercial interne
            $table->foreignId('owner_user_id')->nullable()->after('main_seller_id')->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('owner_user_id')->constrained('users')->nullOnDelete();
            $table->string('client_contact_name')->nullable()->after('client_agency_id');
            $table->string('client_contact_email')->nullable()->after('client_contact_name');
            $table->string('client_contact_phone', 60)->nullable()->after('client_contact_email');
            $table->string('sale_type', 10)->nullable()->after('client_contact_phone');   // b2b | b2c | null = à définir
            $table->foreignId('pricing_client_group_id')->nullable()->after('sale_type')->constrained('client_groups')->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->index(['origin', 'ops_status']);
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->string('lead_traveler_name')->nullable()->change();
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('folders')->whereNull('lead_traveler_name')->orWhereNull('start_date')->orWhereNull('end_date')->exists()) {
            throw new RuntimeException('Des dossiers sans voyageur principal ou sans dates existent : restaurer la sauvegarde au lieu de revenir en arrière.');
        }

        Schema::table('folders', function (Blueprint $table) {
            $table->string('lead_traveler_name')->nullable(false)->change();
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->dropIndex(['origin', 'ops_status']);
            $table->dropConstrainedForeignId('pricing_client_group_id');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn(['kind', 'ops_status', 'client_contact_name', 'client_contact_email', 'client_contact_phone', 'sale_type', 'internal_notes', 'lock_version']);
        });
    }
};
