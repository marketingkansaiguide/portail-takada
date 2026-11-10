<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lignes libres d'un dossier (ex. carnet de voyage) : « à fournir », sans fournisseur, sans commande, sans mission guide,
 * sans événement d'agenda. Table à part : aucun flux de commande ne la lit. Purement additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_free_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->date('service_date')->nullable();
            $table->unsignedInteger('pax')->default(1);
            $table->integer('sale_price')->nullable();
            $table->integer('purchase_price')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['folder_id', 'service_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_free_lines');
    }
};
