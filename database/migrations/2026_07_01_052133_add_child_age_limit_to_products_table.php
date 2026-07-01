<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'child_age_limit')) {
                // Par défaut, un enfant a 11 ans maximum (à 12 ans, il devient adulte)
                $table->integer('child_age_limit')->default(11)->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'child_age_limit')) {
                $table->dropColumn('child_age_limit');
            }
        });
    }
};