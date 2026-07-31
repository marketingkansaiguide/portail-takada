<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            // Rend product_id facultatif pour les prestations internes
            $table->unsignedBigInteger('product_id')->nullable()->change();

            if (!Schema::hasColumn('folder_items', 'is_internal')) {
                $table->boolean('is_internal')->default(false)->after('folder_id');
            }
            if (!Schema::hasColumn('folder_items', 'title')) {
                $table->string('title')->nullable()->after('product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            if (Schema::hasColumn('folder_items', 'is_internal')) {
                $table->dropColumn('is_internal');
            }
            if (Schema::hasColumn('folder_items', 'title')) {
                $table->dropColumn('title');
            }
        });
    }
};