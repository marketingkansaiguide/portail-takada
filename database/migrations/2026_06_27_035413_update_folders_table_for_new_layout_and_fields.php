<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->string('folder_name')->nullable();
            $table->string('hotel_booking_name')->nullable();
            $table->string('contact_phone')->nullable();
            
            // Nettoyage cosmétique de l'ancienne colonne de contacts multiples si elle existe
            if (Schema::hasColumn('folders', 'contact_phones')) {
                $table->dropColumn('contact_phones');
            }
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropColumn(['folder_name', 'hotel_booking_name', 'contact_phone']);
        });
    }
};