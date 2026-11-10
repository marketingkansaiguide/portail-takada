<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consigne urgente billetterie : texte court distinct des consignes générales.
 * Le drapeau `ticketing_urgent` est conservé ; pour les dossiers déjà urgents, le texte
 * existant est repris dans la nouvelle colonne (les consignes générales ne sont pas modifiées).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->string('ticketing_urgent_note', 500)->nullable()->after('ticketing_urgent');
        });

        DB::table('folders')
            ->where('ticketing_urgent', true)
            ->whereNotNull('ticketing_general_notes')
            ->orderBy('id')
            ->each(fn ($f) => DB::table('folders')->where('id', $f->id)
                ->update(['ticketing_urgent_note' => mb_substr((string) $f->ticketing_general_notes, 0, 500)]));
    }

    public function down(): void
    {
        Schema::table('folders', fn (Blueprint $table) => $table->dropColumn('ticketing_urgent_note'));
    }
};
