<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('lead_traveler_name');
            $table->integer('pax_adults')->default(1);
            $table->integer('pax_children')->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('draft'); // draft, pending, confirmed, completed, cancelled
            $table->integer('folder_fee')->default(0); // Capturé depuis le groupe de l'agence
            $table->integer('total_price')->default(0); // Prix total calculé automatiquement
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};