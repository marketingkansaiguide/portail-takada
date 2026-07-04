<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_messages', function (Blueprint $table) {
            $table->id();
            // Lien avec le dossier
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            // Lien avec l'utilisateur (Admin ou Agence) qui écrit le message
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_messages');
    }
};