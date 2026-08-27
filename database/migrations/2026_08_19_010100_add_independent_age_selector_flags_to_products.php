<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'enable_child_selector')) {
                $table->boolean('enable_child_selector')->default(false)->after('enable_age_selector');
            }

            if (! Schema::hasColumn('products', 'enable_teen_selector')) {
                $table->boolean('enable_teen_selector')->default(false)->after('enable_child_selector');
            }
        });

        DB::table('products')
            ->where('enable_age_selector', true)
            ->where('enable_child_selector', false)
            ->update(['enable_child_selector' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'enable_teen_selector')) {
                $table->dropColumn('enable_teen_selector');
            }

            if (Schema::hasColumn('products', 'enable_child_selector')) {
                $table->dropColumn('enable_child_selector');
            }
        });
    }
};
