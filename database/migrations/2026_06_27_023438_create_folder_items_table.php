<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity')->default(1);
            $table->date('service_date');
            $table->integer('unit_price')->default(0);
            $table->integer('total_price')->default(0);
            $table->json('custom_values')->nullable(); // Stockera les réponses dynamiques (kimonos, etc.)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_items');
    }
};