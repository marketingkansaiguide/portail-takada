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
        Schema::create('folder_tasks', function (Blueprint $table) {$table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();$table->string('action_code')->unique(); // Empêche de générer 2 fois la même tâche
            $table->string('description');
            $table->string('icon')->nullable();$table->string('color')->nullable();
            $table->boolean('is_completed')->default(false);$table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folder_tasks');
    }
};
