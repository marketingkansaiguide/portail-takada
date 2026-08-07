<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            $table->timestamp('label_exported_at')->nullable()->after('item_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            $table->dropColumn('label_exported_at');
        });
    }
};