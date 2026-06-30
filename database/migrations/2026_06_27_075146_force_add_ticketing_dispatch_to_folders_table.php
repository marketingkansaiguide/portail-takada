<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            // On force l'ajout si la colonne de méthode n'existe pas encore
            if (!Schema::hasColumn('folders', 'ticket_dispatch_method')) {
                $table->string('ticket_dispatch_method')->nullable()->after('status');
            }

            // On force l'ajout si la colonne optionnelle "Autre" n'existe pas encore
            if (!Schema::hasColumn('folders', 'ticket_dispatch_other')) {
                $table->string('ticket_dispatch_other')->nullable()->after('ticket_dispatch_method');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropColumn(['ticket_dispatch_method', 'ticket_dispatch_other']);
        });
    }
};