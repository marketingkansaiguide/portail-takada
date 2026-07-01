<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            if (!Schema::hasColumn('folder_items', 'selected_options')) {
                $table->json('selected_options')->nullable()->after('product_option_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            if (Schema::hasColumn('folder_items', 'selected_options')) {
                $table->dropColumn('selected_options');
            }
        });
    }
};