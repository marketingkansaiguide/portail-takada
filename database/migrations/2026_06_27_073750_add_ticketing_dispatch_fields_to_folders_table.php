<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->string('ticket_dispatch_method')->nullable()->after('status');
            $table->string('ticket_dispatch_other')->nullable()->after('ticket_dispatch_method');
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropColumn(['ticket_dispatch_method', 'ticket_dispatch_other']);
        });
    }
};