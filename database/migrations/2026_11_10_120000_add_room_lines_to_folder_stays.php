<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plusieurs catégories de chambre par séjour : `room_lines` = [{room_type_id, label, quantity}].
 * Les colonnes actuelles (rooms, room_type_id, room_label) restent alimentées par la première ligne.
 * Les séjours existants reçoivent une ligne issue de leurs colonnes. Additive et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folder_stays', fn (Blueprint $t) => $t->json('room_lines')->nullable());

        DB::table('folder_stays')->whereNull('room_lines')->orderBy('id')->each(function ($stay) {
            if ($stay->room_type_id || filled($stay->room_label) || (int) $stay->rooms > 0) {
                DB::table('folder_stays')->where('id', $stay->id)->update(['room_lines' => json_encode([[
                    'room_type_id' => $stay->room_type_id,
                    'label' => $stay->room_label,
                    'quantity' => max(1, (int) $stay->rooms),
                ]], JSON_UNESCAPED_UNICODE)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('folder_stays', fn (Blueprint $t) => $t->dropColumn('room_lines'));
    }
};
