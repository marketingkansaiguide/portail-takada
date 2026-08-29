<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->integer('purchase_price')->nullable()->after('price');
            $table->string('pricing_mode')->default('per_pax')->after('purchase_price');
            $table->integer('extra_pax_price')->nullable()->after('pricing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'pricing_mode', 'extra_pax_price']);
        });
    }
};
