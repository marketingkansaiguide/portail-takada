<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            if (!Schema::hasColumn('product_options', 'group_name')) {
                $table->string('group_name')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('product_options', 'is_required')) {
                $table->boolean('is_required')->default(false)->after('billing_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            if (Schema::hasColumn('product_options', 'group_name')) {
                $table->dropColumn('group_name');
            }
            if (Schema::hasColumn('product_options', 'is_required')) {
                $table->dropColumn('is_required');
            }
        });
    }
};