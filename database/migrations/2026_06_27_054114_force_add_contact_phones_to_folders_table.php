<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            // Si la colonne n'existe vraiment pas, on la force au format JSON
            if (!Schema::hasColumn('folders', 'contact_phones')) {
                $table->json('contact_phones')->nullable()->after('agency_id');
            }
            
            // Sécurité additionnelle : on nettoie l'ancien champ unique s'il traîne encore
            if (Schema::hasColumn('folders', 'contact_phone')) {
                $table->dropColumn('contact_phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            if (Schema::hasColumn('folders', 'contact_phones')) {
                $table->dropColumn('contact_phones');
            }
        });
    }
};