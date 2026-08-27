<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'teen_age_limit')) {
                $table->unsignedInteger('teen_age_limit')->nullable()->after('child_age_limit');
            }
        });

        Schema::table('product_options', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_options', 'teen_price_modifier')) {
                $table->integer('teen_price_modifier')->nullable()->after('child_price_modifier');
            }
        });

        Schema::table('folder_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('folder_items', 'pax_teenagers')) {
                $table->unsignedInteger('pax_teenagers')->nullable()->after('pax_children');
            }
        });

    }

    public function down(): void
    {
        Schema::table('folder_items', function (Blueprint $table): void {
            if (Schema::hasColumn('folder_items', 'pax_teenagers')) {
                $table->dropColumn('pax_teenagers');
            }
        });

        Schema::table('product_options', function (Blueprint $table): void {
            if (Schema::hasColumn('product_options', 'teen_price_modifier')) {
                $table->dropColumn('teen_price_modifier');
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'teen_age_limit')) {
                $table->dropColumn('teen_age_limit');
            }
        });
    }
};
