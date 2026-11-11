<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi billetterie par prestation (reprise du RÉCAP RÉSAS de l'Excel).
 * - requested_at : envoi de la demande (fax ou mail) au fournisseur
 * - supplier_confirmed_at : confirmation par le fournisseur
 * - purchased_at : achat sans facture (CB, espèces) ; sinon invoice_received_at (existant)
 * - delivered_at : envoi de la billetterie à l'hôtel ou au guide
 * Additive et réversible : aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    private const COLUMNS = ['requested_at', 'supplier_confirmed_at', 'purchased_at', 'delivered_at'];

    public function up(): void
    {
        Schema::table('folder_items', function (Blueprint $t) {
            foreach (self::COLUMNS as $column) {
                $t->date($column)->nullable();
            }
        });

        // Droit d'accès à l'écran « Récap réservations » : mêmes rôles que les billets de train.
        foreach (DB::table('roles')->get() as $role) {
            $current = json_decode($role->permissions ?: '[]', true) ?: [];
            if (in_array('operations.train_tickets', $current, true)) {
                DB::table('roles')->where('id', $role->id)->update([
                    'permissions' => json_encode(array_values(array_unique(array_merge($current, ['operations.recap'])))),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('folder_items', fn (Blueprint $t) => $t->dropColumn(self::COLUMNS));

        foreach (DB::table('roles')->get() as $role) {
            $current = json_decode($role->permissions ?: '[]', true) ?: [];
            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_diff($current, ['operations.recap']))),
            ]);
        }
    }
};
