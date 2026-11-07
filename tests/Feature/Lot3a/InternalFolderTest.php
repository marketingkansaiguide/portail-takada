<?php

use App\Filament\Resources\Folders\FolderResource;
use App\Filament\Resources\Folders\Pages\CreateInternalFolder;
use App\Filament\Resources\Folders\Pages\EditFolder;
use App\Jobs\CancelGuideMission;
use App\Models\Agency;
use App\Models\Folder;
use App\Models\FolderTask;
use App\Models\User;
use App\Services\ActionBoardTaskGenerator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->fit = World::staff('commercial_fit');
    $this->groupe = World::staff('commercial_groupe');
    $this->agency = World::agency('Agence A');
    $this->agencyUser = World::agencyUser($this->agency);
});

it("crée un dossier interne avec seulement un type et un intitulé", function () {
    $this->actingAs($this->fit, 'web');

    Livewire::test(CreateInternalFolder::class)
        ->fillForm(['kind' => 'services_only', 'folder_name' => 'Billets Ghibli famille Roux', 'owner_user_id' => $this->fit->id, 'ops_status' => 'brouillon'])
        ->call('create')
        ->assertHasNoFormErrors();

    $folder = Folder::where('folder_name', 'Billets Ghibli famille Roux')->firstOrFail();
    expect($folder->origin)->toBe('internal')
        ->and($folder->kind)->toBe('services_only')
        ->and($folder->agency_id)->toBeNull()
        ->and($folder->status)->toBe('draft')
        ->and($folder->owner_user_id)->toBe($this->fit->id)
        ->and($folder->created_by_user_id)->toBe($this->fit->id)
        ->and($folder->reference)->toMatch('/^TKD-/')
        ->and($folder->histories()->count())->toBe(1);
});

it("ignore toute tentative d'imposer une agence propriétaire depuis le formulaire interne", function () {
    $this->actingAs($this->fit, 'web');

    Livewire::test(CreateInternalFolder::class)
        ->fillForm(['kind' => 'full_trip', 'folder_name' => 'Tentative', 'owner_user_id' => $this->fit->id, 'ops_status' => 'brouillon', 'client_agency_id' => $this->agency->id])
        ->set('data.agency_id', $this->agency->id)
        ->set('data.main_seller_id', $this->agencyUser->id)
        ->call('create');

    $folder = Folder::where('folder_name', 'Tentative')->firstOrFail();
    expect($folder->agency_id)->toBeNull()
        ->and($folder->main_seller_id)->toBeNull()
        ->and($folder->client_agency_id)->toBe($this->agency->id)
        ->and($this->agencyUser->can('view', $folder))->toBeFalse();
});

it("refuse la création interne à un profil sans droit", function () {
    $this->actingAs(World::staff('marketing_it'), 'web');

    $this->get(FolderResource::getUrl('create-internal'))->assertForbidden();
});

it("permet à tous les commerciaux, quel que soit leur segment, de voir et modifier tous les dossiers internes", function () {
    $byGroupe = World::internalFolder(null, ['owner_user_id' => $this->groupe->id]);
    $b2bGroupe = World::folder($this->agency, ['segment' => 'groupe']);

    $this->actingAs($this->fit, 'web');
    $visible = FolderResource::getEloquentQuery()->pluck('id');

    expect($visible)->toContain($byGroupe->id)
        ->and($visible)->not->toContain($b2bGroupe->id) // le périmètre B2B par segment est inchangé
        ->and($this->fit->can('view', $byGroupe))->toBeTrue()
        ->and($this->fit->can('update', $byGroupe))->toBeTrue()
        ->and($this->fit->can('delete', $byGroupe))->toBeFalse();
});

it("affiche la fiche interne sans agence émettrice, messagerie ni facturation agence", function () {
    $folder = World::internalFolder($this->agency, ['owner_user_id' => $this->fit->id]);
    $this->actingAs($this->fit, 'web');

    Livewire::test(EditFolder::class, ['record' => $folder->getRouteKey()])
        ->assertSee(__('Dossier interne'))
        ->assertDontSee(__('Agence émettrice'))
        ->assertDontSee(__('Messagerie Agence'))
        ->assertDontSee(__('Statut & Facturation'));
});

it("enregistre la fiche interne et trace l'auteur dans l'historique", function () {
    $folder = World::internalFolder(null, ['owner_user_id' => $this->fit->id]);
    $this->actingAs($this->groupe, 'web');

    Livewire::test(EditFolder::class, ['record' => $folder->getRouteKey()])
        ->fillForm(['owner_user_id' => $this->groupe->id, 'ops_status' => 'en_preparation', 'internal_notes' => 'Client fidèle'])
        ->call('save')
        ->assertHasNoFormErrors();

    $folder->refresh();
    $history = $folder->histories()->latest('id')->first();
    expect($folder->owner_user_id)->toBe($this->groupe->id)
        ->and($folder->ops_status)->toBe('en_preparation')
        ->and($folder->status)->toBe('draft')
        ->and($folder->lock_version)->toBe(1)
        ->and($history->user_id)->toBe($this->groupe->id);
});

it("refuse un enregistrement à partir d'une version périmée du dossier", function () {
    $folder = World::internalFolder(null, ['owner_user_id' => $this->fit->id]);
    $this->actingAs($this->fit, 'web');

    $first = Livewire::test(EditFolder::class, ['record' => $folder->getRouteKey()]);
    $second = Livewire::test(EditFolder::class, ['record' => $folder->getRouteKey()]);

    $second->fillForm(['internal_notes' => 'Version B'])->call('save')->assertHasNoFormErrors();
    $first->fillForm(['internal_notes' => 'Version A'])->call('save')->assertNotified(__('Le dossier a été modifié entre-temps'));

    expect($folder->fresh()->internal_notes)->toBe('Version B');
});

it("ne génère pas de tâche « nouveau dossier » pour un brouillon interne et ne le clôture pas automatiquement", function () {
    $folder = World::internalFolder(null, ['end_date' => now()->subDays(3)->toDateString()]);
    if (! User::whereKey(1)->exists()) {
        \Illuminate\Support\Facades\DB::table('users')->insert(['id' => 1, 'name' => 'Admin', 'email' => 'admin1@example.test', 'password' => 'x', 'role' => 'super_admin']);
    }

    app(ActionBoardTaskGenerator::class)->generate();
    $cron = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($event) => $event->command === null && $event->expression === '0 0 * * *');
    $cron->run(app());

    expect(FolderTask::where('action_code', "new_folder_{$folder->id}")->exists())->toBeFalse()
        ->and($folder->fresh()->status)->toBe('draft');
});

it("annule et clôture via le statut commercial sans toucher aux prestations", function () {
    Queue::fake();
    $folder = World::internalFolder();

    $folder->update(['ops_status' => 'annule']);
    expect($folder->fresh()->status)->toBe('cancelled');

    $folder->update(['ops_status' => 'en_preparation']);
    expect($folder->fresh()->status)->toBe('draft');

    $folder->update(['ops_status' => 'termine']);
    expect($folder->fresh()->status)->toBe('completed');
});

it("signale une politique tarifaire non définie au lieu d'utiliser l'agence associée", function () {
    $folder = World::internalFolder($this->agency);

    expect($folder->hasUndeterminedPricingPolicy())->toBeTrue()
        ->and($folder->pricing_client_group_id)->toBeNull();

    $folder->update(['sale_type' => 'b2b', 'pricing_client_group_id' => $this->agency->client_group_id]);
    expect($folder->fresh()->hasUndeterminedPricingPolicy())->toBeFalse();
});

it("affiche la liste mixte (B2B et interne) avec les deux boutons de création", function () {
    World::internalFolder($this->agency, ['folder_name' => 'INTERNE-LISTE']);
    World::folder($this->agency, ['folder_name' => 'B2B-LISTE', 'segment' => 'fit']);
    $this->actingAs($this->fit, 'web');

    $this->get(\App\Filament\Commercial\Resources\CommercialFolderResource::getUrl('index', panel: 'commercial'))
        ->assertOk()
        ->assertSee('INTERNE-LISTE')
        ->assertSee('B2B-LISTE')
        ->assertSee(__('Nouveau dossier interne'))
        ->assertSee(__('Nouveau dossier agence (B2B)'));
});

it("produit un PDF récapitulatif pour un dossier interne sans agence", function () {
    $folder = World::internalFolder(null, ['client_contact_name' => 'Claire Martin']);
    $this->actingAs(World::staff('super_admin'), 'web');

    $this->get(route('pdf.recapitulatif', $folder))->assertOk();
});
