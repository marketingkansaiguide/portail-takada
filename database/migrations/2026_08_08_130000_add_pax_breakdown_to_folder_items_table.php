<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            if (!Schema::hasColumn('folder_items', 'pax_adults')) {
                $table->unsignedInteger('pax_adults')->nullable()->after('quantity');
            }

            if (!Schema::hasColumn('folder_items', 'pax_children')) {
                $table->unsignedInteger('pax_children')->nullable()->after('pax_adults');
            }
        });
    }

    public function down(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('folder_items', 'pax_children')) {
                $columns[] = 'pax_children';
            }

            if (Schema::hasColumn('folder_items', 'pax_adults')) {
                $columns[] = 'pax_adults';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
