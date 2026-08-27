<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_group_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unique(['client_group_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_group_product');
    }
};
