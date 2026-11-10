<?php

use App\Filament\Commercial\Resources\CommercialFolderResource\Pages\EditCommercialFolder;
use App\Models\FolderItem;
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
    $this->folder = World::internalFolder(null, ['owner_user_id' => $this->fit->id, 'ops_status' => 'en_preparation', 'kind' => 'full_trip', 'start_date' => '2027-04-01', 'end_date' => '2027-04-10']);
    $this->planned = Product::where('category_id', 1)->orderBy('id')->take(3)->get()
        ->map(fn ($p, $i) => World::plannedItem($this->folder, $p, ['service_date' => '2027-04-0'.($i + 3), 'ticketing_notes' => 'NOTE-'.$i, 'meeting_point' => 'RDV-'.$i]));
});

function page($test)
{
    return Livewire::test(EditCommercialFolder::class, ['record' => $test->folder->getRouteKey()]);
}

it('sélectionne des lignes, affiche la barre et commande seulement la sélection', function () {
    $ids = $this->planned->pluck('id');
    $component = page($this)
        ->call('togglePlannedSelection', $ids[0])
        ->call('togglePlannedSelection', $ids[2])
        ->assertSet('selectedPlannedIds', [$ids[0], $ids[2]])
        ->assertSee('2 sélectionnées')
        ->call('orderSelected')
        ->assertActionMounted('orderPlannedItems');

    $component->callMountedAction()->assertHasNoActionErrors();

    expect(FolderItem::where('folder_id', $this->folder->id)->pluck('planned_item_id')->sort()->values()->all())
        ->toBe([$ids[0], $ids[2]])
        ->and($this->planned[1]->fresh()->state)->toBe('draft');
});

it('ignore un identifiant étranger au dossier et propose tout sélectionner / désélectionner', function () {
    page($this)
        ->call('togglePlannedSelection', 999999)
        ->assertSet('selectedPlannedIds', [])
        ->call('selectAllPlanned', true)
        ->assertCount('selectedPlannedIds', 3)
        ->call('selectAllPlanned', false)
        ->assertSet('selectedPlannedIds', []);
});

it('refuse la commande si le dossier a été modifié entre-temps et ne double pas au rejeu', function () {
    $ids = $this->planned->pluck('id');
    $c = page($this)->call('togglePlannedSelection', $ids[0])->call('orderSelected')->assertActionMounted('orderPlannedItems');
    \App\Models\Folder::whereKey($this->folder->id)->increment('lock_version');
    $c->callMountedAction();
    expect(FolderItem::where('folder_id', $this->folder->id)->count())->toBe(0);
});

it('affiche les alertes d’hébergement avec un lien d’ouverture et un récapitulatif sans colonne achat', function () {
    $this->folder->stays()->create(['hotel_name' => 'Hôtel Sans Départ', 'check_in' => '2027-04-01', 'status' => 'to_book', 'booked_by' => 'takada']);
    $ids = $this->planned->pluck('id');

    page($this)
        ->assertSee('Hôtel sans date de départ')
        ->assertSee('tkOpenTarget')
        ->call('togglePlannedSelection', $ids[0])
        ->call('orderSelected')
        ->assertActionMounted('orderPlannedItems')
        ->assertSee('Total')
        ->assertDontSee('>Achat<', false);
});

it('un double clic sur Commander (même clé d’idempotence) ne crée qu’une commande', function () {
    $id = $this->planned[0]->id;
    $folder = $this->folder->fresh();
    $key = (string) \Illuminate\Support\Str::uuid();
    $service = app(\App\Services\PlannedItemOrderService::class);

    $first = $service->order($folder, [$id], $this->fit, (int) $folder->lock_version, $key, true);
    $second = $service->order($this->folder->fresh(), [$id], $this->fit, (int) $folder->lock_version, $key, true);

    expect($first['status'])->toBe('completed')
        ->and($second['status'])->toBe('completed')
        ->and(FolderItem::where('planned_item_id', $id)->count())->toBe(1);
});

it('côté admin : consignes générales en lecture et note de la prestation commandée sur la ligne repliée', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->folder->update(['ticketing_urgent' => true, 'ticketing_urgent_note' => 'Bus 23/12', 'ticketing_general_notes' => 'Consigne générale du commercial']);
    $user = \App\Models\User::where('role', 'super_admin')->first() ?? World::staff('super_admin');
    $this->actingAs($user, 'web');
    $folder = $this->folder->fresh();
    app(\App\Services\PlannedItemOrderService::class)->order($folder, [$this->planned[0]->id], $user, (int) $folder->lock_version, (string) \Illuminate\Support\Str::uuid(), true);

    Livewire::test(\App\Filament\Resources\Folders\Pages\EditFolder::class, ['record' => $this->folder->getRouteKey()])
        ->assertSee('Bus 23/12')
        ->assertSee('Consigne générale du commercial')
        ->assertSee('NOTE-0')
        ->assertSee('RDV-0');
});
