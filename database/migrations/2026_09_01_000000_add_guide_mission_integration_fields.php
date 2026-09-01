<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('guide_city')->nullable()->after('name');
        });

        Schema::table('product_options', function (Blueprint $table): void {
            $table->time('guide_start_time')->nullable()->after('code');
            $table->time('guide_end_time')->nullable()->after('guide_start_time');
        });

        Schema::table('folder_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('guide_mission_id')->nullable()->after('google_calendar_event_id')->index();
            $table->string('guide_sync_status', 20)->nullable()->after('guide_mission_id')->index();
            $table->text('guide_sync_error')->nullable()->after('guide_sync_status');
            $table->timestamp('guide_synced_at')->nullable()->after('guide_sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('folder_items', function (Blueprint $table): void {
            $table->dropIndex(['guide_mission_id']);
            $table->dropIndex(['guide_sync_status']);
            $table->dropColumn(['guide_mission_id', 'guide_sync_status', 'guide_sync_error', 'guide_synced_at']);
        });
        Schema::table('product_options', fn (Blueprint $table) => $table->dropColumn(['guide_start_time', 'guide_end_time']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('guide_city'));
    }
};
