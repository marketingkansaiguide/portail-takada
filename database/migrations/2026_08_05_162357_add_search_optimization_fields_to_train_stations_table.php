<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('train_stations', function (Blueprint $table) {
            $table->string('city')->nullable()->after('prefecture');
            $table->string('aliases')->nullable()->after('name_kana');
            $table->integer('importance_score')->default(10)->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('train_stations', function (Blueprint $table) {
            $table->dropColumn(['city', 'aliases', 'importance_score']);
        });
    }
};