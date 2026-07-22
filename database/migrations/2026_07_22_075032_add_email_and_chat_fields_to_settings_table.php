<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('admin_email_notifications')->nullable()->after('general_cancellation_policy');
            $table->integer('chat_reminder_hours')->default(48)->after('admin_email_notifications');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['admin_email_notifications', 'chat_reminder_hours']);
        });
    }
};