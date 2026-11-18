<?php

use App\Support\TransportFareCalendar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calendrier de saisons JR par tarif : « A », « B », « K » (A sans saison basse) ou null (pas de saison,
 * prix normal toute l'année). Les lignes seasonal existantes sont classées d'après le nom du train ;
 * les lignes flat restent à null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_fares', function (Blueprint $t) {
            $t->string('season_calendar', 1)->nullable()->after('pricing_mode');
        });

        DB::table('transport_fares')->where('pricing_mode', 'seasonal')->orderBy('id')->each(function ($fare) {
            DB::table('transport_fares')->where('id', $fare->id)->update([
                'season_calendar' => TransportFareCalendar::forTrain($fare->train_name, 'seasonal'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('transport_fares', function (Blueprint $t) {
            $t->dropColumn('season_calendar');
        });
    }
};
