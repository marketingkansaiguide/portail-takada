<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL applies each schema instruction independently. If a prior run
        // stopped after adding this column, resume safely from that state.
        if (! Schema::hasColumn('product_option_season_prices', 'purchase_price_modifier')) {
            Schema::table('product_option_season_prices', function (Blueprint $table) {
                $table->integer('purchase_price_modifier')->nullable()->after('price_modifier');
            });
        }

        Schema::table('product_option_season_prices', function (Blueprint $table) {
            // The old unique key is also the supporting index of the option FK.
            // Keep a dedicated index while replacing it with the tier-aware key.
            $table->index('product_option_id', 'option_season_price_option_id_index');
            $table->dropUnique('option_period_season_price_unique');
            $table->unique(
                ['product_option_id', 'product_period_id', 'product_price_id'],
                'option_period_tier_season_price_unique',
            );
        });

        DB::table('product_option_season_prices')
            ->whereNull('adult_price_modifier')
            ->whereNotNull('price_modifier')
            ->update(['adult_price_modifier' => DB::raw('price_modifier')]);
    }

    public function down(): void
    {
        Schema::table('product_option_season_prices', function (Blueprint $table) {
            $table->dropUnique('option_period_tier_season_price_unique');
            $table->unique(['product_option_id', 'product_period_id'], 'option_period_season_price_unique');
            $table->dropColumn('purchase_price_modifier');
        });
    }
};
