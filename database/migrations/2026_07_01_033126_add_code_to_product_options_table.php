<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            if (!Schema::hasColumn('product_options', 'code')) {
                $table->string('code')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            if (Schema::hasColumn('product_options', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};