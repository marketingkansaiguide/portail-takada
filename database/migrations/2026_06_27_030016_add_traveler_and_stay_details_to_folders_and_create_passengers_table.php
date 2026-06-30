<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 🏢 1. On enrichit la table folders avec les détails globaux du séjour et des contacts
        Schema::table('folders', function (Blueprint $table) {
            $table->json('contact_phones')->nullable();
            $table->text('flight_info')->nullable();
            $table->date('first_hotel_check_in')->nullable();
            $table->string('first_hotel_name')->nullable();
            $table->text('first_hotel_address')->nullable();
        });

        // 👥 2. On crée la table pour stocker chaque voyageur individuellement
        Schema::create('folder_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date');
            $table->string('nationality');
            $table->text('dietary_restrictions')->nullable();
            $table->text('mobility_concerns')->nullable(); // Handicap / mobilité réduite
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_passengers');

        Schema::table('folders', function (Blueprint $table) {
            $table->dropColumn([
                'contact_phones',
                'flight_info',
                'first_hotel_check_in',
                'first_hotel_name',
                'first_hotel_address'
            ]);
        });
    }
};