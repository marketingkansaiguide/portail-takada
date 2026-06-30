<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // 'Création' ou 'Mise à jour'
            $table->json('changes_payload')->nullable(); // On stockera l'avant/après ici
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_histories');
    }
};