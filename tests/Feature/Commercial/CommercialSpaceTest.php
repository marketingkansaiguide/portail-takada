<?php

use App\Filament\Commercial\Resources\CommercialFolderResource;
use App\Filament\Commercial\Resources\CommercialFolderResource\Pages\CreateCommercialInternalFolder;
use App\Filament\Commercial\Resources\CommercialFolderResource\Pages\EditCommercialFolder;
use App\Models\Folder;
use App\Models\FolderItem;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->fit = World::staff('commercial_fit');
    $this->agency = World::agency('Agence A');
    $this->agencyUser = World::agencyUser($this->agency);
});

it("ouvre l'espace commercial aux commerciaux et leur ferme l'administration", function () {
    $this->actingAs($this->fit, 'web');

    $this->get('/commercial')->assertOk()->assertSee('Espace commercial');
    $this->get(CommercialFolderResource::getUrl('index', panel: 'commercial'))->assertOk()->assertSee(__('Nouveau dossier interne'));
    $this->get('/admin')->assertRedirect('/commercial');
    $this->get('/admin/folders')->assertRedirect('/commercial');
});

it("garde l'administration pour les profils qui y avaient accès", function () {
    foreach (['admin', 'billetterie', 'agent', 'direction', 'marketing_it', 'super_admin'] as $role) {
        $user = World::staff($role);
        expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue("{$role} doit garder /admin");
    }
});

it("garde l'administration à un commercial qui cumule un autre profil back-office", function () {
    $both = World::staff('commercial_fit');
    DB::table('role_user')->insert(['user_id' => $both->id, 'role_id' => 'billetterie']);
    $both = $both->fresh();

    expect($both->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and($both->canAccessPanel(Filament::getPanel('commercial')))->toBeTrue();
});

it("refuse l'espace commercial aux agences et aux profils sans dossiers internes", function () {
    expect($this->agencyUser->canAccessPanel(Filament::getPanel('commercial')))->toBeFalse()
        ->and(World::staff('marketing_it')->canAccessPanel(Filament::getPanel('commercial')))->toBeFalse();

    $this->actingAs($this->agencyUser, 'agency');
    $this->get('/commercial')->assertRedirect();
});

it("crée, prépare et commande un dossier interne depuis l'espace commercial", function () {
    Filament::setCurrentPanel(Filament::getPanel('commercial'));
    $this->actingAs($this->fit, 'web');

    Livewire::test(CreateCommercialInternalFolder::class)
        ->fillForm(['kind' => 'full_trip', 'folder_name' => 'Espace commercial — test', 'owner_user_id' => $this->fit->id, 'start_date' => '2027-04-01', 'end_date' => '2027-04-10', 'pax_adults' => 2, 'pax_children' => 1])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $folder = Folder::where('folder_name', 'Espace commercial — test')->firstOrFail();
    $folder->update(['sale_type' => 'b2c', 'pricing_client_group_id' => (int) DB::table('client_groups')->value('id')]);
    $planned = World::plannedItem($folder, World::activityProduct());

    Livewire::test(EditCommercialFolder::class, ['record' => $folder->getRouteKey()])
        ->assertSee(__('À commander'))
        ->callAction('orderPlannedItems', data: ['planned_ids' => [$planned->id]])
        ->assertHasNoActionErrors()
        ->assertRedirect(CommercialFolderResource::getUrl('edit', ['record' => $folder], panel: 'commercial'));

    expect(FolderItem::where('planned_item_id', $planned->id)->exists())->toBeTrue();
});
