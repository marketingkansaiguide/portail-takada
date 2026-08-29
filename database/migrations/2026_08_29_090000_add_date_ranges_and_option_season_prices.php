<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_periods', function (Blueprint $table) {
            $table->json('date_ranges')->nullable()->after('end_date');
        });

        DB::table('product_periods')->orderBy('id')->each(function (object $period): void {
            if (filled($period->start_date) && filled($period->end_date)) {
                DB::table('product_periods')->where('id', $period->id)->update([
                    'date_ranges' => json_encode([[
                        'start_date' => $period->start_date,
                        'end_date' => $period->end_date,
                    ]]),
                ]);
            }
        });

        Schema::create('product_option_season_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_period_id')->constrained()->cascadeOnDelete();
            $table->integer('price_modifier')->nullable();
            $table->integer('adult_price_modifier')->nullable();
            $table->integer('child_price_modifier')->nullable();
            $table->integer('teen_price_modifier')->nullable();
            $table->timestamps();
            $table->unique(['product_option_id', 'product_period_id'], 'option_period_season_price_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_season_prices');

        Schema::table('product_periods', function (Blueprint $table) {
            $table->dropColumn('date_ranges');
        });
    }
};
