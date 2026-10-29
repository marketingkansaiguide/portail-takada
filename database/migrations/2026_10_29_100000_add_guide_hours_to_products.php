<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->time('guide_start_time')->nullable()->after('guide_city');
            $table->time('guide_end_time')->nullable()->after('guide_start_time');
            $table->boolean('guide_all_day')->default(false)->after('guide_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['guide_start_time', 'guide_end_time', 'guide_all_day']);
        });
    }
};
