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
        Schema::create('train_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ja')->nullable();
            $table->string('name_kana')->nullable();
            $table->string('category')->nullable();
            $table->string('prefecture')->nullable();
            $table->text('address')->nullable();
            $table->text('google_maps_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('train_stations');
    }
};