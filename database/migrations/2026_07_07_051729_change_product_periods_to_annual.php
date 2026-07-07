<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. On supprime les anciennes colonnes dates bloquées sur une année précise
        Schema::table('product_periods', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });

        // 2. On les recrée en format texte (5 caractères pour stocker "MM-DD")
        Schema::table('product_periods', function (Blueprint $table) {
            $table->string('start_date', 5)->after('name')->nullable();
            $table->string('end_date', 5)->after('start_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_periods', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });

        Schema::table('product_periods', function (Blueprint $table) {
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });
    }
};