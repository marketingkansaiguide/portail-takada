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
        Schema::table('product_periods', function (Blueprint $table) {
            // Force la conversion des colonnes en VARCHAR(5) pour stocker les formats "MM-DD"
            $table->string('start_date', 5)->nullable()->change();
            $table->string('end_date', 5)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_periods', function (Blueprint $table) {
            // Retour au format Date d'origine en cas de rollback
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });
    }
};