<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            $table->integer('purchase_unit_price')->default(0)->after('quantity');
            $table->integer('purchase_total_price')->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            $table->dropColumn(['purchase_unit_price', 'purchase_total_price']);
        });
    }
};