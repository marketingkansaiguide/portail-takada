<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            // Liaison stricte avec la table des produits
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            
            $table->string('name'); // Ex: Option Premium, En Privé...
            $table->integer('price_modifier')->default(0); // Le supplément en Yens (ex: 2000)
            $table->string('billing_type')->default('per_pax'); // per_pax ou per_booking
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};