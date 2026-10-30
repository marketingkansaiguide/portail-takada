<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_periods', function (Blueprint $table): void {
            // Période qui remplace la grille de base quand l'option indiquée est choisie (ex: cérémonie privée).
            $table->unsignedBigInteger('variant_option_id')->nullable()->index()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_periods', function (Blueprint $table): void {
            $table->dropIndex(['variant_option_id']);
            $table->dropColumn('variant_option_id');
        });
    }
};
