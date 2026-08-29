<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_option_season_prices', function (Blueprint $table) {
            $table->foreignId('product_price_id')->nullable()->after('product_period_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_option_season_prices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_price_id');
        });
    }
};
