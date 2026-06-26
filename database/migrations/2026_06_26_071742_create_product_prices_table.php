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
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_period_id')->constrained()->cascadeOnDelete();
            $table->integer('min_pax')->default(1);
            $table->integer('max_pax')->default(99);
            $table->integer('min_age')->default(0);
            $table->integer('max_age')->default(99);
            $table->integer('price'); // Prix net en Yens
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
