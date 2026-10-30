<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_options', function (Blueprint $table): void {
            $table->json('available_days')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('product_options', function (Blueprint $table): void {
            $table->dropColumn('available_days');
        });
    }
};
