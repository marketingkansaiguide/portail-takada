<?php

use App\Filament\Commercial\CommercialFolderForm;
use App\Filament\Commercial\Resources\CommercialFolderResource;
use App\Filament\Commercial\Resources\CommercialFolderResource\Pages\EditCommercialFolder;
use App\Models\Folder;
use App\Models\FolderFlightSegment;
use App\Models\FolderItem;
use App\Models\FolderStay;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Support\FolderFinanceSummary;
use App\Support\MeetingSuggestions;
use App\Support\TripTimeline;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->fit = World::staff('commercial_fit');
    $this->folder = World::internalFolder(null, ['start_date' => '2026-12-17', 'end_date' => '2027-01-03', 'owner_user_id' => $this->fit->id, 'ops_status' => 'en_preparation', 'kind' => 'full_trip']);
});

it("prépare et commande sans groupe client ni type de vente, frais de dossier nuls", function () {
    expect($this->folder->pricing_client_group_id)->toBeNull()->and($this->folder->sale_type)->toBeNull();

    $item = World::orderedInternalItem($this->folder, World::activityProduct());

    expect($item->exists)->toBeTrue()
        ->and((float) $this->folder->fresh()->folder_fee)->toBe(0.0);
});

it("conserve les notes billetterie et le rendez-vous à la commande", function () {
    $planned = World::plannedItem($this->folder, World::activityProduct(), [
        'ticketing_notes' => 'Prise en charge à l’hôtel, enfants de 8 et 13 ans.',
        'meeting_point' => 'Hôtel Exemple — 1-1 Asakusa',
        'meeting_time' => '09:30',
    ]);
    $user = \App\Models\User::where('role', 'super_admin')->first() ?? World::staff('super_admin');
    $folder = $this->folder->fresh();

    app(\App\Services\PlannedItemOrderService::class)->order($folder, [$planned->id], $user, (int) $folder->lock_version, (string) \Illuminate\Support\Str::uuid(), true);

    $ordered = FolderItem::where('planned_item_id', $planned->id)->firstOrFail();
    expect($ordered->ticketing_notes)->toBe('Prise en charge à l’hôtel, enfants de 8 et 13 ans.')
        ->and($ordered->meeting_point)->toBe('Hôtel Exemple — 1-1 Asakusa')
        ->and($ordered->meeting_time)->toBe('09:30');

    // Modifier la fiche hôtel ensuite ne réécrit pas la valeur de la commande.
    expect(FolderItem::find($ordered->id)->meeting_point)->toBe('Hôtel Exemple — 1-1 Asakusa');
});

it("gère plusieurs hébergements dont un réservé par le client, sans le compter comme à commander", function () {
    $hotel = Hotel::create(['name' => 'Hôtel Exemple Asakusa', 'city' => 'Tokyo', 'address' => '1-1 Asakusa']);
    $room = HotelRoomType::create(['hotel_id' => $hotel->id, 'name' => 'Twin']);

    $this->folder->stays()->create(['hotel_id' => $hotel->id, 'hotel_name' => $hotel->name, 'city' => 'Tokyo', 'check_in' => '2026-12-17', 'check_out' => '2026-12-21', 'room_type_id' => $room->id, 'room_label' => 'Twin', 'status' => 'confirmed']);
    $this->folder->stays()->create(['hotel_name' => 'Location libre Hakone', 'check_in' => '2026-12-29', 'check_out' => '2027-01-01', 'status' => 'booked_by_client', 'booked_by' => 'client']);
    $this->folder->stays()->create(['hotel_id' => $hotel->id, 'hotel_name' => $hotel->name, 'check_in' => '2027-01-01', 'check_out' => '2027-01-03', 'status' => 'to_book']);

    $stays = $this->folder->stays()->get();
    expect($stays)->toHaveCount(3)
        ->and($stays[0]->nights())->toBe(4)
        ->and($stays[1]->needsTakadaBooking())->toBeFalse()
        ->and($stays[2]->needsTakadaBooking())->toBeTrue()
        ->and($stays->where('hotel_id', $hotel->id))->toHaveCount(2); // même hôtel, dates différentes

    // Le nom conservé reste lisible si la fiche hôtel change.
    $hotel->update(['name' => 'Autre nom']);
    expect($this->folder->stays()->first()->hotel_name)->toBe('Hôtel Exemple Asakusa');
});

it("recherche les hôtels par nom, variante ou ville sans créer de doublon", function () {
    Hotel::create(['name' => 'Hôtel Exemple Kanazawa', 'city' => 'Kanazawa', 'alt_names' => 'Exemple Kana']);
    Hotel::create(['name' => 'Machiya Exemple', 'city' => 'Kyoto']);
    $before = Hotel::count();

    expect(array_values(CommercialFolderForm::searchHotels('kanazawa')))->toHaveCount(1)
        ->and(array_values(CommercialFolderForm::searchHotels('exemple kana')))->toHaveCount(1)
        ->and(CommercialFolderForm::searchHotels('Kyoto'))->toHaveCount(1)
        ->and(CommercialFolderForm::searchHotels('zzz'))->toBe([])
        ->and(Hotel::count())->toBe($before);
});

it("calcule l'heure locale et la durée réelle d'un vol avec les fuseaux", function () {
    $seg = $this->folder->flightSegments()->create([
        'direction' => 'outbound', 'airline' => 'SriLankan', 'flight_number' => 'UL454',
        'departure_airport' => 'CMB', 'arrival_airport' => 'NRT',
        'departure_local' => '2026-12-16 23:30', 'departure_tz' => FolderFlightSegment::timezoneFor('CMB'),
        'arrival_local' => '2026-12-17 08:10', 'arrival_tz' => FolderFlightSegment::timezoneFor('NRT'),
    ]);

    // 23:30 Colombo (UTC+5:30) = 18:00 UTC ; 08:10 Tokyo (UTC+9) = 23:10 UTC la veille : 5h10 de vol.
    expect($seg->durationMinutes())->toBe(310);
});

it("propose hôtels du dossier puis vols pour un rendez-vous, le séjour en cours en premier", function () {
    $options = MeetingSuggestions::options('2026-12-23', [
        ['hotel_name' => 'Hôtel A', 'city' => 'Tokyo', 'check_in' => '2026-12-17', 'check_out' => '2026-12-21', 'address' => ''],
        ['hotel_name' => 'Hôtel B', 'city' => 'Kanazawa', 'check_in' => '2026-12-21', 'check_out' => '2026-12-24', 'address' => 'Quartier X'],
    ], [
        ['arrival_airport' => 'NRT', 'flight_number' => 'UL454', 'arrival_local' => '2026-12-17 08:10'],
    ]);

    expect(array_key_first($options))->toBe('Hôtel B — Quartier X')
        ->and(array_keys($options))->toContain('Hôtel A')
        ->and(collect(array_keys($options))->contains(fn ($k) => str_contains($k, 'UL454')))->toBeTrue();
});

it("signale les incohérences dans la chronologie et distingue préparé et commandé", function () {
    $this->folder->stays()->create(['hotel_name' => 'Hôtel A', 'check_in' => '2026-12-17', 'check_out' => '2026-12-20', 'status' => 'to_book']);
    World::plannedItem($this->folder, World::guideProduct(), ['service_date' => '2026-12-18']);

    $timeline = TripTimeline::build($this->folder->fresh());
    $alerts = implode(' | ', array_column($timeline['alerts'], 'label'));

    expect($alerts)->toContain('Aucun vol')->and($alerts)->toContain('Nuits sans hébergement')->and($alerts)->toContain('Hôtel à réserver');
    $states = collect($timeline['days'])->flatMap(fn ($d) => collect($d['entries'])->pluck('state'));
    expect($states)->toContain('planned');
});

it("n'affiche pas un achat à 0 ¥ quand aucun coût n'est renseigné", function () {
    $planned = World::plannedItem($this->folder, World::activityProduct());
    $planned->update(['purchase_total_price' => null]);

    $summary = FolderFinanceSummary::for($this->folder->fresh());

    expect($summary['planned']['state'])->toBe('unknown')->and($summary['planned']['cost'])->toBeNull()
        ->and($summary['ordered']['state'])->toBe('none');
});

it("affiche la fiche commerciale simplifiée sans pré-facture, chat ni fournisseur", function () {
    Filament::setCurrentPanel(Filament::getPanel('commercial'));
    $this->actingAs($this->fit, 'web');

    Livewire::test(EditCommercialFolder::class, ['record' => $this->folder->getRouteKey()])
        ->assertSuccessful()
        ->assertSee(__('Hébergements'))
        ->assertSee(__('Vols'))
        ->assertSee(__('Historique des modifications'))
        ->assertDontSee('Pré-facture')
        ->assertDontSee('Messagerie')
        ->assertDontSee('Fournisseur de la prestation');
});

it("enregistre voyageurs sans date de naissance, vols, hébergements et consignes depuis la fiche", function () {
    Filament::setCurrentPanel(Filament::getPanel('commercial'));
    $this->actingAs($this->fit, 'web');

    Livewire::test(EditCommercialFolder::class, ['record' => $this->folder->getRouteKey()])
        ->fillForm([
            'quote_reference' => 'DEV-TEST-1',
            'ticketing_urgent' => true,
            'ticketing_general_notes' => 'Réserver le bus du 23/12.',
            'folderPassengers' => [['last_name' => 'Exemple', 'first_name' => 'Anna', 'is_lead' => true]],
            'flightSegments' => [['direction' => 'outbound', 'flight_number' => 'XX1', 'departure_airport' => 'CMB', 'arrival_airport' => 'NRT']],
            'stays' => [['hotel_name' => 'Hôtel A', 'check_in' => '2026-12-17', 'check_out' => '2026-12-21', 'status' => 'booked_by_client', 'booked_by' => 'client', 'rooms' => 1]],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $folder = $this->folder->fresh();
    expect($folder->quote_reference)->toBe('DEV-TEST-1')
        ->and($folder->ticketing_urgent)->toBeTrue()
        ->and($folder->folderPassengers()->count())->toBe(1)
        ->and($folder->folderPassengers()->first()->birth_date)->toBeNull()
        ->and($folder->flightSegments()->count())->toBe(1)
        ->and($folder->stays()->first()->booked_by)->toBe('client');
});

function tlAlerts($folder): array
{
    return TripTimeline::build($folder->fresh())['alerts'];
}

it("T3 : un séjour sans départ donne une alerte sur le séjour et aucune nuit manquante déduite", function () {
    $this->folder->stays()->create(['hotel_name' => 'Hôtel Exemple Asakusa', 'check_in' => '2026-12-17', 'status' => 'confirmed', 'booked_by' => 'takada']);
    $this->folder->stays()->create(['hotel_name' => 'Hôtel Kanazawa', 'check_in' => '2026-12-21', 'check_out' => '2027-01-03', 'status' => 'confirmed', 'booked_by' => 'takada']);

    $alerts = tlAlerts($this->folder);
    $types = array_column($alerts, 'type');

    expect($types)->toContain('stay_missing_checkout')->and($types)->not->toContain('nights_missing');
    $a = collect($alerts)->firstWhere('type', 'stay_missing_checkout');
    expect($a['label'])->toContain('Hôtel Exemple Asakusa')->and($a['target']['kind'])->toBe('stay')->and($a['target']['id'])->not->toBeNull();
});

it("T3 : un séjour sans aucune date est signalé distinctement", function () {
    $this->folder->stays()->create(['hotel_name' => 'Machiya Exemple', 'status' => 'to_book', 'booked_by' => 'takada']);
    expect(array_column(tlAlerts($this->folder), 'type'))->toContain('stay_missing_dates');
});

it("T3 : un trou réel est regroupé en une plage, jamais tronqué", function () {
    $this->folder->stays()->create(['hotel_name' => 'A', 'check_in' => '2026-12-17', 'check_out' => '2026-12-25', 'status' => 'confirmed', 'booked_by' => 'takada']);
    $this->folder->stays()->create(['hotel_name' => 'B', 'check_in' => '2026-12-29', 'check_out' => '2027-01-03', 'status' => 'confirmed', 'booked_by' => 'takada']);

    $gaps = array_values(array_filter(tlAlerts($this->folder), fn ($a) => $a['type'] === 'nights_missing'));

    expect($gaps)->toHaveCount(1)
        ->and($gaps[0]['range'])->toMatchArray(['from' => '2026-12-25', 'to' => '2026-12-28', 'nights' => 4])
        ->and($gaps[0]['label'])->toContain('25/12 → 28/12')->toContain('4');
});

it("T3 : un séjour réservé par le client couvre ses nuits sans action de commande", function () {
    $this->folder->stays()->create(['hotel_name' => 'Location libre', 'check_in' => '2026-12-17', 'check_out' => '2027-01-03', 'status' => 'booked_by_client', 'booked_by' => 'client']);
    $types = array_column(tlAlerts($this->folder), 'type');
    expect($types)->not->toContain('nights_missing')->and($types)->not->toContain('stay_to_book');
});
