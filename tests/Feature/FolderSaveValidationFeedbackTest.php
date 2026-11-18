<?php

use App\Filament\Resources\Folders\Pages\EditFolder;
use App\Models\ItemStatus;
use App\Models\Product;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(World::staff('super_admin'), 'web');
    $this->folder = World::folder(World::agency('A'));
});

it("enregistre le changement de statut d'une prestation", function () {
    $product = Product::where('category_id', 1)->get()->first(fn ($p) => collect($p->custom_field_definitions ?? [])->where('is_required', true)->isEmpty());
    $item = World::item($this->folder, $product);
    $status = ItemStatus::create(['name' => 'Confirmé', 'color' => 'success']);

    $page = Livewire::test(EditFolder::class, ['record' => $this->folder->getKey()]);
    $key = array_key_first($page->get('data.folderItems'));
    $page->set('data.ticket_dispatch_method', 'hotel')->set("data.folderItems.$key.item_status_id", $status->id)->call('save')->assertHasNoFormErrors();

    expect($item->fresh()->item_status_id)->toBe($status->id);
});

it("annonce l'enregistrement impossible quand un champ obligatoire d'une prestation est vide", function () {
    $product = Product::where('category_id', 1)->get()->first(fn ($p) => collect($p->custom_field_definitions ?? [])->where('is_required', true)->isNotEmpty());
    $item = World::item($this->folder, $product);
    $status = ItemStatus::create(['name' => 'Confirmé', 'color' => 'success']);

    $page = Livewire::test(EditFolder::class, ['record' => $this->folder->getKey()]);
    $key = array_key_first($page->get('data.folderItems'));
    $page->set('data.ticket_dispatch_method', 'hotel')->set("data.folderItems.$key.item_status_id", $status->id)->call('save')
        ->assertNotified(__('Enregistrement impossible : des champs sont à compléter'));

    expect($item->fresh()->item_status_id)->not->toBe($status->id);
});

it("signale dans la vue jour par jour les infos manquantes et les éléments en attente", function () {
    $product = Product::where('category_id', 1)->get()->first(fn ($p) => collect($p->custom_field_definitions ?? [])->where('is_required', true)->isNotEmpty());
    World::item($this->folder, $product);
    $requiredName = collect($product->custom_field_definitions)->firstWhere('is_required', true)['name'];

    $html = (string) \App\Filament\Resources\Folders\FolderDayByDay::render(
        $this->folder->folderItems()->get()->map->toArray()->all(), [], '2027-04-01', '2027-04-10',
    );

    expect($html)->toContain(__('À traiter'))
        ->and($html)->toContain(__('Manque : :field', ['field' => $requiredName]));
});
