<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table): void {
            if (! Schema::hasColumn('folders', 'ticket_dispatch_guide_id')) {
                $table->unsignedBigInteger('ticket_dispatch_guide_id')->nullable()->after('ticket_dispatch_method');
            }

            if (! Schema::hasColumn('folders', 'ticket_dispatch_guide_name')) {
                $table->string('ticket_dispatch_guide_name')->nullable()->after('ticket_dispatch_guide_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table): void {
            if (Schema::hasColumn('folders', 'ticket_dispatch_guide_name')) {
                $table->dropColumn('ticket_dispatch_guide_name');
            }

            if (Schema::hasColumn('folders', 'ticket_dispatch_guide_id')) {
                $table->dropColumn('ticket_dispatch_guide_id');
            }
        });
    }
};
