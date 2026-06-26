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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // Ex: train, activite, wifi
            $table->json('images')->nullable(); // Pour la galerie photo
            $table->text('description')->nullable();
            $table->string('cancellation_type')->default('general'); // general ou specific
            $table->text('cancellation_specifics')->nullable();
            $table->boolean('is_lottery')->default(false);
            $table->boolean('is_on_demand')->default(false);
            $table->integer('days_before_opening')->nullable(); // Ex: 30 jours
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
