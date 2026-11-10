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
        ->assertSee('Aucune prestation sélectionnée')
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
