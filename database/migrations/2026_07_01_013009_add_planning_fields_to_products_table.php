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
        Schema::table('products', function (Blueprint $table) {
            // On vérifie que la colonne n'existe pas déjà par sécurité
            if (!Schema::hasColumn('products', 'available_days')) {
                $table->json('available_days')->nullable()->after('description');
            }
            if (!Schema::hasColumn('products', 'blackout_dates')) {
                $table->json('blackout_dates')->nullable()->after('available_days');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['available_days', 'blackout_dates']);
        });
    }
};