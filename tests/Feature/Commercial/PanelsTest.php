<?php

use App\Filament\Commercial\Resources\CommercialFolderResource\Pages\EditCommercialFolder;
use App\Models\Folder;
use App\Models\FolderPlannedItem;
use App\Models\Product;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->fit = World::staff('commercial_fit');
    $this->actingAs($this->fit, 'web');
    Filament::setCurrentPanel(Filament::getPanel('commercial'));
    $this->folder = World::internalFolder(null, [
        'owner_user_id' => $this->fit->id, 'ops_status' => 'en_preparation', 'kind' => 'full_trip', 'folder_name' => 'Famille EXEMPLE',
        'start_date' => '2026-12-17', 'end_date' => '2027-01-03', 'pax_adults' => 2, 'pax_children' => 2,
    ]);
    $this->page = fn () => Livewire::test(EditCommercialFolder::class, ['record' => $this->folder->getRouteKey()]);
});

it('a pour titre le nom du voyage, sans bouton d’enregistrement global, et la consigne urgente sur la fiche', function () {
    $this->folder->update(['ticketing_urgent' => true, 'ticketing_urgent_note' => 'Bus du 23/12']);

    $component = ($this->page)();
    expect($component->instance()->getTitle())->toBe('Famille EXEMPLE');
    $component->assertSee('Bus du 23/12')->assertSee('Modifier le dossier')->assertDontSee('Sauvegarder les modifications');
    $labels = array_map(fn ($a) => $a->getLabel(), (new ReflectionMethod($component->instance(), 'getFormActions'))->invoke($component->instance()));
    expect($labels)->toBe([]);
});

it('affiche sept onglets courts', function () {
    ($this->page)()->assertSee('Synthèse')->assertSee('Voyageurs')->assertSee('Vols')->assertSee('Hôtels')->assertSee('Prestations')->assertSee('Planning')->assertSee('Finances');
});

it('ajoute une prestation par la saisie rapide (pax du dossier, date) puis ouvre son panneau', function () {
    $product = Product::where('is_public', true)->where('category_id', 1)->first();

    $component = ($this->page)()
        ->assertSet('quickAdd.pax', 4)
        ->set('quickAdd.product_id', $product->id)
        ->set('quickAdd.service_date', '2026-12-18')
        ->call('quickAddItem')
        ->assertActionMounted('editPlanned');

    $planned = FolderPlannedItem::where('folder_id', $this->folder->id)->firstOrFail();
    expect($planned->product_id)->toBe($product->id)->and((int) $planned->quantity)->toBe(4)->and($planned->service_date->format('Y-m-d'))->toBe('2026-12-18')
        ->and($planned->state)->toBe('draft');
});

it('enregistre une prestation depuis son panneau, sous le verrou du dossier, et n’autorise pas une commande', function () {
    $planned = World::plannedItem($this->folder, World::activityProduct(), ['service_date' => '2026-12-18', 'quantity' => 4]);
    $lock = (int) $this->folder->fresh()->lock_version;

    ($this->page)()
        ->mountAction('editPlanned', ['id' => $planned->id])
        ->assertActionDataSet(fn (array $data) => $data['plannedItems']['item']['service_date'] === '2026-12-18' && (int) $data['plannedItems']['item']['quantity'] === 4)
        ->setActionData(['_lock' => $lock, 'plannedItems' => ['item' => ['meeting_point' => 'Hôtel Exemple', 'meeting_time' => '09:30', 'ticketing_notes' => 'Prise en charge à l’hôtel'] + $planned->only(['product_id', 'service_date', 'quantity'])]])
        ->callMountedAction()->assertHasNoActionErrors();

    $planned->refresh();
    expect($planned->meeting_point)->toBe('Hôtel Exemple')->and($planned->meeting_time)->toBe('09:30')->and($planned->ticketing_notes)->toBe('Prise en charge à l’hôtel')
        ->and($planned->state)->toBe('draft')
        ->and((int) $this->folder->fresh()->lock_version)->toBe($lock + 1);
});

it('refuse l’enregistrement d’une prestation sur une version périmée du dossier', function () {
    $planned = World::plannedItem($this->folder, World::activityProduct(), ['service_date' => '2026-12-18']);
    $stale = (int) $this->folder->fresh()->lock_version;
    Folder::whereKey($this->folder->id)->increment('lock_version');

    ($this->page)()
        ->mountAction('editPlanned', ['id' => $planned->id])
        ->setActionData(['_lock' => $stale, 'plannedItems' => ['item' => ['meeting_point' => 'Ne doit pas être enregistré', 'product_id' => $planned->product_id, 'service_date' => '2026-12-18', 'quantity' => 4]]])
        ->callMountedAction();

    expect($planned->fresh()->meeting_point)->not->toBe('Ne doit pas être enregistré');
});

it('supprime une ligne préparée sans toucher aux commandes', function () {
    $planned = World::plannedItem($this->folder, World::activityProduct(), ['service_date' => '2026-12-18']);

    ($this->page)()->callAction('deletePlanned', arguments: ['id' => $planned->id]);

    expect(FolderPlannedItem::whereKey($planned->id)->exists())->toBeFalse();
});

it('ajoute une note de suivi dans l’historique', function () {
    ($this->page)()->callAction('addNote', data: ['note' => 'Client rappelé le 10/11']);

    expect($this->folder->histories()->get()->contains(fn ($h) => str_contains((string) json_encode($h->changes_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Client rappelé le 10/11')))->toBeTrue();
});

it('affiche le planning : tous les jours, la nuit en fin de journée, tri par heure et incohérences sur le jour', function () {
    $this->folder->update(['start_date' => '2026-12-17', 'end_date' => '2026-12-20']);
    $this->folder->stays()->create(['hotel_name' => 'Hôtel A', 'city' => 'Tokyo', 'check_in' => '2026-12-17', 'check_out' => '2026-12-19', 'status' => 'confirmed', 'booked_by' => 'takada']);
    $this->folder->flightSegments()->create(['direction' => 'outbound', 'flight_number' => 'XX454', 'departure_airport' => 'CMB', 'arrival_airport' => 'NRT', 'arrival_local' => '2026-12-17 08:10', 'departure_local' => '2026-12-16 22:00']);
    World::plannedItem($this->folder, World::activityProduct(), ['service_date' => '2026-12-17', 'meeting_time' => '07:30']); // avant l'arrivée du vol
    World::plannedItem($this->folder, World::activityProduct(), ['service_date' => '2026-12-17', 'meeting_time' => '09:00']);
    World::plannedItem($this->folder, World::activityProduct(), ['service_date' => '2026-12-17', 'meeting_time' => '09:00']);

    $planning = \App\Support\TripTimeline::planning($this->folder->fresh());
    $byDate = collect($planning)->keyBy(fn ($d) => $d['date']->format('Y-m-d'));

    expect($byDate->keys()->all())->toContain('2026-12-17', '2026-12-18', '2026-12-19', '2026-12-20')   // tous les jours, même sans prestation
        ->and($byDate['2026-12-18']['entries'])->toBe([])
        ->and($byDate['2026-12-17']['night']['kind'])->toBe('stay')
        ->and($byDate['2026-12-19']['night']['kind'])->toBe('missing')                                 // trou d'hébergement visible sur son jour
        ->and($byDate['2026-12-20']['night'])->toBeNull();                                              // dernier jour : pas de nuit
    $times = collect($byDate['2026-12-17']['entries'])->pluck('time')->filter()->values()->all();
    expect($times)->toBe(collect($times)->sort()->values()->all());                                     // tri par heure réelle
    $warnings = implode(' | ', $byDate['2026-12-17']['warnings']);
    expect($warnings)->toContain('avant l’arrivée du vol')->and($warnings)->toContain('même heure');
});
