<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            // On s'assure de la présence de la colonne JSON pour les numéros multiples
            if (!Schema::hasColumn('folders', 'contact_phones')) {
                $table->json('contact_phones')->nullable();
            }
            
            // Nettoyage de la colonne de téléphone unique précédente
            if (Schema::hasColumn('folders', 'contact_phone')) {
                $table->dropColumn('contact_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->string('contact_phone')->nullable();
            $table->dropColumn('contact_phones');
        });
    }
};