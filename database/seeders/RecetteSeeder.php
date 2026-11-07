<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Folder;
use App\Models\FolderItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Catalogue;

/**
 * Données de recette LOCALES (jamais en production) : catalogue réel sans
 * fournisseurs, un compte par profil, deux agences, des dossiers B2B et internes.
 *
 * Usage : php artisan migrate:fresh --seed --seeder=Database\\Seeders\\RecetteSeeder
 * Mot de passe de tous les comptes de recette : self::PASSWORD
 */
class RecetteSeeder extends Seeder
{
    public const PASSWORD = 'recette-locale-2026';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('RecetteSeeder ne doit jamais être exécuté en production.');
        }

        Catalogue::load();

        $make = function (string $role, string $name, ?int $agencyId = null): User {
            $user = User::create([
                'name' => $name,
                'email' => str_replace('_', '-', $role) . ($agencyId ? "-{$agencyId}" : '') . '@recette.test',
                'password' => self::PASSWORD,
                'role' => $role,
                'agency_id' => $agencyId,
            ]);
            DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $role]);

            return $user;
        };

        $admin = $make('super_admin', 'Admin Recette');
        $fit = $make('commercial_fit', 'Lucie (Commercial FIT)');
        $groupe = $make('commercial_groupe', 'Kenji (Commercial Groupe)');
        $make('billetterie', 'Billetterie Recette');
        $make('direction', 'Direction Recette');
        $make('marketing_it', 'Marketing Recette');

        $groupId = (int) DB::table('client_groups')->orderBy('id')->value('id');
        $voyages = Agency::create(['name' => 'Voyages Lumière', 'email' => 'contact@voyages-lumiere.test', 'client_group_id' => $groupId, 'is_approved' => true]);
        $horizon = Agency::create(['name' => 'Horizon Japon', 'email' => 'contact@horizon-japon.test', 'client_group_id' => $groupId, 'is_approved' => true]);
        $seller = $make('agency', 'Vendeur Voyages Lumière', $voyages->id);
        $make('agency', 'Vendeur Horizon Japon', $horizon->id);

        auth('web')->login($admin);

        $b2b = Folder::create([
            'origin' => Folder::ORIGIN_B2B, 'agency_id' => $voyages->id, 'main_seller_id' => $seller->id,
            'folder_name' => 'DUBOIS — Kyoto & Osaka', 'lead_traveler_name' => 'Marc Dubois', 'segment' => 'billetterie',
            'status' => 'confirmed', 'pax_adults' => 2, 'pax_children' => 0, 'start_date' => '2027-04-02', 'end_date' => '2027-04-12',
            'ticket_dispatch_method' => 'hotel',
        ]);
        $activity = \App\Models\Product::where('category_id', 1)->orderBy('id')->first();
        FolderItem::create(['folder_id' => $b2b->id, 'product_id' => $activity->id, 'service_date' => '2027-04-05', 'quantity' => 2, 'pax_adults' => 2, 'unit_price' => 9000, 'total_price' => 18000, 'purchase_unit_price' => 6000, 'purchase_total_price' => 12000]);

        Folder::create([
            'origin' => Folder::ORIGIN_B2B, 'agency_id' => $horizon->id, 'folder_name' => 'LEROY — Tokyo', 'lead_traveler_name' => 'Anne Leroy',
            'segment' => 'fit', 'status' => 'draft', 'pax_adults' => 3, 'pax_children' => 1, 'start_date' => '2027-05-10', 'end_date' => '2027-05-20',
            'ticket_dispatch_method' => 'hotel',
        ]);

        $martin = Folder::create([
            'origin' => Folder::ORIGIN_INTERNAL, 'kind' => Folder::KIND_FULL_TRIP, 'folder_name' => 'Famille Martin — Japon printemps 2027',
            'sale_type' => 'b2c', 'pricing_client_group_id' => $groupId,
            'owner_user_id' => $fit->id, 'client_agency_id' => $voyages->id, 'client_contact_name' => 'Claire Martin',
            'client_contact_email' => 'claire.martin@exemple.test', 'ops_status' => 'en_preparation', 'start_date' => '2027-04-01',
            'end_date' => '2027-04-15', 'pax_adults' => 2, 'pax_children' => 2, 'internal_notes' => 'Demande via Voyages Lumière, facturation directe à confirmer.',
        ]);

        // Prestations préparées (budget prévisionnel, rien de commandé).
        $dates = ['2027-04-03', '2027-04-05', '2027-04-07', '2027-04-09', '2027-04-11'];
        foreach (\App\Models\Product::where('category_id', 1)->orderBy('id')->take(4)->get() as $i => $product) {
            \Tests\Support\World::plannedItem($martin, $product, ['service_date' => $dates[$i], 'quantity' => 4]);
        }

        Folder::create([
            'origin' => Folder::ORIGIN_INTERNAL, 'kind' => Folder::KIND_SERVICES_ONLY, 'folder_name' => 'Billets Ghibli — Roux',
            'owner_user_id' => $groupe->id, 'sale_type' => 'b2c', 'pricing_client_group_id' => $groupId, 'ops_status' => 'brouillon',
        ]);

        auth('web')->logout();
    }
}
