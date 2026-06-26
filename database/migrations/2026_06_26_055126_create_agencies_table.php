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
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_group_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Nom de l'agence
            $table->string('contact_name')->nullable(); // Nom du contact
            $table->string('email')->unique(); // Email
            $table->string('phone')->nullable(); // Téléphone
            $table->text('address')->nullable(); // L'adresse que tu as demandée
            $table->boolean('is_approved')->default(false); // Statut (Validé ou non)
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};
