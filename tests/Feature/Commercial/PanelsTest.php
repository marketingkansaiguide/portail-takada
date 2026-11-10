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

it('crée un dossier en un écran, sans politique tarifaire, et arrive sur la fiche', function () {
    $page = Livewire::test(\App\Filament\Commercial\Resources\CommercialFolderResource\Pages\CreateCommercialInternalFolder::class);
    $page->assertDontSee('Politique tarifaire')->assertDontSee('groupe client')
        ->fillForm(['folder_name' => 'Voyage Test', 'kind' => 'full_trip', 'owner_user_id' => $this->fit->id, 'start_date' => '2027-04-01', 'end_date' => '2027-04-10', 'pax_adults' => 2, 'pax_children' => 1])
        ->call('create')->assertHasNoFormErrors()->assertRedirect();

    $created = Folder::where('folder_name', 'Voyage Test')->firstOrFail();
    expect($created->pricing_client_group_id)->toBeNull()->and($created->start_date->format('Y-m-d'))->toBe('2027-04-01')->and((int) $created->pax_children)->toBe(1)
        ->and($created->ops_status)->toBe('brouillon');
});

it('liste les dossiers sans pré-facture, avec le voyage d’abord, la référence en fin et « — » pour un montant inconnu', function () {
    Livewire::test(\App\Filament\Commercial\Resources\CommercialFolderResource\Pages\ListCommercialFolders::class)
        ->assertSee('Famille EXEMPLE')->assertSee($this->folder->reference)->assertSee('—')
        ->assertDontSee('Pré-facture')->assertDontSee('draft')->assertDontSee('confirmed');
});

it('déduit le fuseau d’un vol du code aéroport sans le demander', function () {
    $lock = (int) $this->folder->fresh()->lock_version;
    ($this->page)()->callAction('editFlight', data: ['_lock' => $lock, 'direction' => 'outbound', 'flight_number' => 'XX1', 'departure_airport' => 'CMB', 'arrival_airport' => 'NRT',
        'departure_local' => '2026-12-17 08:10', 'arrival_local' => '2026-12-17 16:30'])->assertHasNoActionErrors();

    $flight = $this->folder->flightSegments()->first();
    expect($flight->arrival_tz)->toBe('Asia/Tokyo')->and($flight->departure_tz)->not->toBeNull();
});

it('met à jour le nombre de voyageurs du dossier depuis les voyageurs saisis', function () {
    $this->folder->folderPassengers()->create(['last_name' => 'A', 'first_name' => 'Adulte', 'birth_date' => '1980-01-01']);
    $this->folder->folderPassengers()->create(['last_name' => 'B', 'first_name' => 'Enfant', 'birth_date' => '2018-01-01']);

    ($this->page)()->assertSee('Mettre à jour le nombre de voyageurs du dossier')->call('syncPaxCount');

    $folder = $this->folder->fresh();
    expect((int) $folder->pax_adults)->toBe(1)->and((int) $folder->pax_children)->toBe(1);
});

it('permet au commercial de saisir les prix de vente et d’achat pour ce dossier, sans jamais modifier le produit', function () {
    $product = World::activityProduct();
    $before = Product::whereKey($product->id)->first()->getAttributes();
    $beforePrices = DB::table('product_prices')->orderBy('id')->get()->toArray();
    $planned = World::plannedItem($this->folder, $product, ['service_date' => '2026-12-18', 'quantity' => 4]);
    $lock = (int) $this->folder->fresh()->lock_version;

    ($this->page)()
        ->mountAction('editPlanned', ['id' => $planned->id])
        ->setActionData(['_lock' => $lock, 'plannedItems' => ['item' => [
            'product_id' => $product->id, 'service_date' => '2026-12-18', 'quantity' => 4, 'total_price' => 12345, 'unit_price' => 3086, 'sale_price_overridden' => true,
            'purchase_total_price' => 8000, 'purchase_unit_price' => 2000, 'purchase_price_overridden' => true,
        ]]])
        ->callMountedAction()->assertHasNoActionErrors();

    $planned->refresh();
    expect((int) $planned->total_price)->toBe(12345)->and((int) $planned->purchase_total_price)->toBe(8000)
        ->and($planned->sale_price_overridden)->toBeTrue()->and($planned->purchase_price_overridden)->toBeTrue();
    // Le produit du catalogue et ses tarifs ne changent jamais.
    expect(Product::whereKey($product->id)->first()->getAttributes())->toBe($before)
        ->and(DB::table('product_prices')->orderBy('id')->get()->toArray())->toEqual($beforePrices);
});

it('le recalcul du tarif ne touche pas un prix saisi à la main', function () {
    $product = World::activityProduct();
    $state = ['product_id' => $product->id, 'service_date' => '2026-12-18', 'quantity' => 2, 'selected_options' => [], 'custom_values' => [],
        'total_price' => 999, 'unit_price' => 500, 'sale_price_overridden' => true, 'purchase_total_price' => 1, 'purchase_price_overridden' => false];
    $get = fn ($k) => $state[$k] ?? null;
    $set = function ($k, $v) use (&$state) { $state[$k] = $v; };

    \App\Filament\Resources\Folders\FolderResource::updateItemPrices($set, $get);

    expect($state['total_price'])->toBe(999)->and($state['unit_price'])->toBe(500);   // vente conservée
});

it('commande au prix saisi, sans alerte de tarif, et le revient au tarif quand le drapeau est retiré', function () {
    $product = World::activityProduct();
    $planned = World::plannedItem($this->folder, $product, ['service_date' => '2026-12-18', 'quantity' => 4]);
    $tariff = (int) $planned->total_price;
    $planned->update(['total_price' => $tariff + 777, 'unit_price' => (int) round(($tariff + 777) / 4), 'sale_price_overridden' => true]);

    $service = app(\App\Services\PlannedItemOrderService::class);
    $summary = $service->summary($this->folder->fresh(), [$planned->id], $this->fit);
    expect($summary['has_price_changes'])->toBeFalse();

    $folder = $this->folder->fresh();
    $result = $service->order($folder, [$planned->id], $this->fit, (int) $folder->lock_version, (string) \Illuminate\Support\Str::uuid(), false);
    expect($result['status'])->toBe('completed')
        ->and((int) \App\Models\FolderItem::where('planned_item_id', $planned->id)->value('total_price'))->toBe($tariff + 777);
});

it('sans surcharge, un écart de tarif reste signalé et le tarif est appliqué à la commande', function () {
    $planned = World::plannedItem($this->folder, World::activityProduct(), ['service_date' => '2026-12-18', 'quantity' => 4]);
    $planned->update(['total_price' => (int) $planned->total_price + 500]);   // écart sans drapeau

    $summary = app(\App\Services\PlannedItemOrderService::class)->summary($this->folder->fresh(), [$planned->id], $this->fit);
    expect($summary['has_price_changes'])->toBeTrue();
});

it('préremplit une ligne de chambres depuis les colonnes d’un séjour existant sans lignes', function () {
    $stay = $this->folder->stays()->create(['hotel_name' => 'Hôtel Exemple', 'check_in' => '2026-12-17', 'check_out' => '2026-12-20', 'status' => 'confirmed', 'booked_by' => 'takada', 'rooms' => 2]);
    \Illuminate\Support\Facades\DB::table('folder_stays')->where('id', $stay->id)->update(['room_lines' => null, 'rooms' => 2]);

    ($this->page)()->mountAction('editStay', ['id' => $stay->id])
        ->assertActionDataSet(fn (array $data) => count($data['room_lines'] ?? []) === 1 && (int) array_values($data['room_lines'])[0]['quantity'] === 2);
});

it('affiche « ch., catégorie à préciser » pour des chambres sans catégorie et n’a plus de carte vide ni de gestionnaire de relations', function () {
    $component = ($this->page)();
    expect($component->instance()->getRelationManagers())->toBe([]);
});

it('bloque la confirmation de commande quand une ligne sélectionnée est bloquée', function () {
    // Ligne sans date : bloquée au récapitulatif.
    $planned = World::plannedItem($this->folder, World::activityProduct(), ['service_date' => '2026-12-18']);
    \Illuminate\Support\Facades\DB::table('folder_planned_items')->where('id', $planned->id)->update(['service_date' => null]);

    $component = ($this->page)()->call('togglePlannedSelection', $planned->id);
    $method = new ReflectionMethod($component->instance(), 'selectionIsBlocking');
    $method->setAccessible(true);
    expect($method->invoke($component->instance(), app(\App\Services\PlannedItemOrderService::class)))->toBeTrue();
});

it('T11 : une ligne libre (carnet de voyage) se saisit, avec ses deux prix, et n’entre jamais dans le flux de commande', function () {
    \Illuminate\Support\Facades\Queue::fake();
    \Illuminate\Support\Facades\Mail::fake();
    $lock = (int) $this->folder->fresh()->lock_version;

    ($this->page)()->callAction('editFreeLine', data: [
        '_lock' => $lock, 'label' => 'Carnet de voyage', 'service_date' => '2026-12-18', 'pax' => 4, 'sale_price' => 5000, 'purchase_price' => 1200, 'note' => 'Impression couleur',
    ])->assertHasNoActionErrors();

    $line = \App\Models\FolderFreeLine::where('folder_id', $this->folder->id)->firstOrFail();
    expect($line->label)->toBe('Carnet de voyage')->and($line->sale_price)->toBe(5000)->and($line->purchase_price)->toBe(1200)->and($line->pax)->toBe(4);

    // Aucun effet hors dossier : ni commande, ni ligne préparée, ni mission, ni agenda, ni file d'attente, ni e-mail.
    expect(\App\Models\FolderItem::where('folder_id', $this->folder->id)->count())->toBe(0)
        ->and(FolderPlannedItem::where('folder_id', $this->folder->id)->count())->toBe(0);
    Queue::assertNothingPushed();
    Mail::assertNothingSent();

    // La sélection et la commande ne la voient pas.
    $component = ($this->page)()->call('selectAllPlanned', true)->assertSet('selectedPlannedIds', []);
});

it('T11 : la ligne libre est visible dans la synthèse et dans le planning, sans prix d’achat côté planning', function () {
    $this->folder->update(['start_date' => '2026-12-17', 'end_date' => '2026-12-20']);
    $this->folder->freeLines()->create(['label' => 'Carnet de voyage', 'service_date' => '2026-12-18', 'pax' => 4, 'sale_price' => 5000]);
    $this->folder->freeLines()->create(['label' => 'Attention client sans date', 'pax' => 1]);

    ($this->page)()->assertSee('Lignes libres à fournir')->assertSee('Carnet de voyage')->assertSee('date à préciser');

    $planning = collect(\App\Support\TripTimeline::planning($this->folder->fresh()))->keyBy(fn ($d) => $d['date']->format('Y-m-d'));
    $labels = collect($planning['2026-12-18']['entries'])->pluck('label')->all();
    expect($labels)->toContain('Carnet de voyage')
        ->and(collect($planning['2026-12-18']['entries'])->firstWhere('label', 'Carnet de voyage')['status'])->toBe('free');
});

it('T11 : supprimer une ligne libre ne touche ni aux commandes ni aux prestations préparées', function () {
    $planned = World::plannedItem($this->folder, World::activityProduct(), ['service_date' => '2026-12-18']);
    $line = $this->folder->freeLines()->create(['label' => 'Carnet', 'pax' => 2]);

    ($this->page)()->callAction('deleteFreeLine', arguments: ['id' => $line->id]);

    expect(\App\Models\FolderFreeLine::count())->toBe(0)->and(FolderPlannedItem::whereKey($planned->id)->exists())->toBeTrue();
});
