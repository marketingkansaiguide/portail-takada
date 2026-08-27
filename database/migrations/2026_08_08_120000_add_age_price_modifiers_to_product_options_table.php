<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            if (!Schema::hasColumn('product_options', 'adult_price_modifier')) {
                $table->integer('adult_price_modifier')->nullable()->after('price_modifier');
            }

            if (!Schema::hasColumn('product_options', 'child_price_modifier')) {
                $table->integer('child_price_modifier')->nullable()->after('adult_price_modifier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('product_options', 'adult_price_modifier')) {
                $columns[] = 'adult_price_modifier';
            }

            if (Schema::hasColumn('product_options', 'child_price_modifier')) {
                $columns[] = 'child_price_modifier';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
