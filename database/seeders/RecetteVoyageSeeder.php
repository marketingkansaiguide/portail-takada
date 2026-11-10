<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\FolderPlannedItem;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\User;
use App\Services\PlannedItemOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Tests\Support\World;

/**
 * Recette de la fiche commerciale : dossier fictif de même structure qu'un envoi réel à la billetterie
 * (4 voyageurs, 2 vols, 5 séjours dont un réservé par le client, prestations datées avec rendez-vous,
 * consigne urgente, commande partielle). Identités et noms d'hôtels fictifs. Jamais en production.
 */
class RecetteVoyageSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('RecetteVoyageSeeder ne doit jamais être exécuté en production.');
        }

        // Recette locale : aucun effet externe. Le .env local pointe vers le vrai Google Agenda
        // et le vrai Planning Guides ; on les neutralise pour ce processus, file d'attente comprise
        // (sync : aucun job ne reste en attente pour un worker qui aurait la vraie configuration).
        config([
            'services.google_calendar.calendar_id' => null,
            'services.guide_planning.base_url' => null,
            'services.guide_planning.api_key' => null,
            'queue.default' => 'sync',
        ]);

        $owner = User::where('email', 'commercial-fit@recette.test')->firstOrFail();

        $hotels = [
            'asakusa' => ['Hôtel Exemple Asakusa', 'Tokyo', '1-1 Asakusa, Taito-ku', ['Double', 'Twin', 'Familiale 4 personnes']],
            'kanazawa' => ['Hôtel Exemple Kanazawa', 'Kanazawa', '2-2 Hirosaka, Kanazawa', ['Double', 'Twin']],
            'omi' => ['Auberge Exemple Omihachiman', 'Omihachiman', '3-3 Omi, Shiga', ['Chambre japonaise']],
            'kyoto' => ['Machiya Exemple Kyoto', 'Kyoto', '4-4 Higashiyama, Kyoto', []],
        ];
        $h = [];
        foreach ($hotels as $key => [$name, $city, $address, $rooms]) {
            $h[$key] = Hotel::firstOrCreate(['name' => $name], ['city' => $city, 'address' => $address]);
            foreach ($rooms as $room) {
                HotelRoomType::firstOrCreate(['hotel_id' => $h[$key]->id, 'name' => $room]);
            }
        }

        $folder = Folder::create([
            'origin' => Folder::ORIGIN_INTERNAL, 'kind' => 'full_trip', 'ops_status' => 'en_preparation',
            'owner_user_id' => $owner->id, 'created_by_user_id' => $owner->id, 'status' => 'draft',
            'folder_name' => 'Famille EXEMPLE — fêtes de fin d’année', 'lead_traveler_name' => 'Anna Exemple',
            'sale_type' => 'b2b', 'quote_reference' => 'DEV-EXEMPLE-0001',
            'start_date' => '2026-12-17', 'end_date' => '2027-01-03', 'pax_adults' => 2, 'pax_children' => 2,
            'ticketing_urgent' => true,
            'ticketing_urgent_note' => 'Bus à réserver pour le 23/12.',
            'ticketing_general_notes' => "ATTENTION BILLETTERIE : bus à réserver pour le 23/12.\nNuits du 29/12 au 01/01 réservées par le client (location libre).",
            'internal_notes' => 'Clients résidant à l’étranger. Téléphone à confirmer.',
        ]);

        foreach ([['Exemple', 'Anna', null, true], ['Exemple', 'Bruno', null, false], ['Exemple', 'Léa', '2013-03-14', false], ['Exemple', 'Hugo', '2017-10-02', false]] as [$ln, $fn, $bd, $lead]) {
            $folder->folderPassengers()->create(['last_name' => $ln, 'first_name' => $fn, 'birth_date' => $bd, 'is_lead' => $lead]);
        }

        $folder->flightSegments()->create(['direction' => 'outbound', 'airline' => 'Compagnie Exemple', 'flight_number' => 'XX454', 'departure_airport' => 'CMB', 'arrival_airport' => 'NRT',
            'departure_tz' => 'Asia/Colombo', 'arrival_tz' => 'Asia/Tokyo', 'arrival_local' => '2026-12-17 08:10']);
        $folder->flightSegments()->create(['direction' => 'return', 'airline' => 'Compagnie Exemple', 'flight_number' => 'XX455', 'departure_airport' => 'NRT', 'arrival_airport' => 'CMB',
            'departure_tz' => 'Asia/Tokyo', 'arrival_tz' => 'Asia/Colombo', 'departure_local' => '2027-01-03 11:20']);

        $stay = fn (array $a) => $folder->stays()->create($a);
        $stay(['hotel_id' => $h['asakusa']->id, 'hotel_name' => $h['asakusa']->name, 'city' => 'Tokyo', 'check_in' => '2026-12-17', 'status' => 'confirmed', 'rooms' => 2]);
        $stay(['hotel_id' => $h['kanazawa']->id, 'hotel_name' => $h['kanazawa']->name, 'city' => 'Kanazawa', 'check_in' => '2026-12-21', 'check_out' => '2026-12-24', 'status' => 'confirmed']);
        $stay(['hotel_id' => $h['omi']->id, 'hotel_name' => $h['omi']->name, 'city' => 'Omihachiman', 'check_in' => '2026-12-24', 'status' => 'confirmed']);
        $stay(['hotel_id' => $h['kyoto']->id, 'hotel_name' => $h['kyoto']->name, 'city' => 'Kyoto', 'status' => 'confirmed']);
        $stay(['hotel_name' => 'Location libre à Hakone', 'city' => 'Hakone', 'check_in' => '2026-12-29', 'check_out' => '2027-01-01', 'status' => 'booked_by_client', 'booked_by' => 'client', 'notes' => 'Réservé par le client (plateforme de location).']);
        $stay(['hotel_id' => $h['asakusa']->id, 'hotel_name' => $h['asakusa']->name, 'city' => 'Tokyo', 'check_in' => '2027-01-01', 'check_out' => '2027-01-03', 'status' => 'confirmed']);

        $route = fn (string $date, string $from, string $to, string $dep, ?string $arr = null) => ['transport_routes' => [['departure_station' => $from, 'arrival_station' => $to, 'departure_date' => $date, 'departure_time' => $dep, 'arrival_time' => $arr, 'pax_count' => 4]]];
        $plan = fn (int $productId, string $date, int $qty, array $extra = []) => World::plannedItem($folder, $productId, ['service_date' => $date, 'quantity' => $qty, 'created_by_user_id' => $owner->id] + $extra);
        $items = [
            $plan(3, '2026-12-17', 4),
            $plan(175, '2026-12-17', 4, ['meeting_point' => 'Aéroport NRT — vol XX454, arrivée 17/12 08:10', 'meeting_time' => '08:30', 'ticketing_notes' => 'Accueil à l’arrivée puis transfert vers l’hôtel de Tokyo.']),
            $plan(139, '2026-12-17', 4, ['meeting_point' => 'Aéroport NRT', 'meeting_time' => '08:30']),
            $plan(160, '2026-12-18', 4, ['meeting_point' => $h['asakusa']->name . ' — ' . $h['asakusa']->address, 'meeting_time' => '09:00']),
            $plan(4, '2026-12-21', 4, ['custom_values' => $route('2026-12-21', 'Tokyo', 'Kanazawa', '09:20', '11:52'), 'ticketing_notes' => 'Shinkansen, places non réservées.']),
            $plan(4, '2026-12-23', 4, ['custom_values' => $route('2026-12-23', 'Kanazawa', 'Shirakawa-go', '09:30'), 'ticketing_notes' => 'Bus Kanazawa → Shirakawa-go vers 09:30, retour vers 15:00. À réserver impérativement.']),
            $plan(4, '2026-12-24', 4, ['custom_values' => $route('2026-12-24', 'Kanazawa', 'Takayama', '09:05', '11:30')]),
            $plan(163, '2026-12-26', 4, ['meeting_point' => $h['kyoto']->name]),
            $plan(4, '2026-12-28', 4, ['custom_values' => $route('2026-12-28', 'Kyoto', 'Hakone', '09:43')]),
            $plan(5, '2026-12-29', 4, ['ticketing_notes' => 'Hakone Free Pass 3 jours.']),
            $plan(4, '2027-01-03', 4, ['custom_values' => $route('2027-01-03', 'Ueno', 'Narita Airport', '07:00'), 'ticketing_notes' => 'Skyliner Ueno → aéroport NRT, départ 07:00.']),
        ];

        // Commande partielle : les trois premières lignes (arrivée) seulement.
        $folder = $folder->fresh();
        app(PlannedItemOrderService::class)->order($folder, collect($items)->take(3)->pluck('id')->all(), $owner, (int) $folder->lock_version, (string) Str::uuid(), true);

        $this->command?->info('Dossier de recette créé : ' . $folder->reference);
    }
}
