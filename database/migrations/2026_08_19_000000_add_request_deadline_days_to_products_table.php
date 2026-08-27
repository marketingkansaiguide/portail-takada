<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'days_before_request_deadline')) {
                $table->unsignedInteger('days_before_request_deadline')
                    ->nullable()
                    ->after('days_before_opening');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'days_before_request_deadline')) {
                $table->dropColumn('days_before_request_deadline');
            }
        });
    }
};
