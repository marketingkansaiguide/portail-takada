<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hotels') || Schema::hasColumn('hotels', 'phone')) {
            return;
        }

        Schema::table('hotels', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hotels') || ! Schema::hasColumn('hotels', 'phone')) {
            return;
        }

        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
