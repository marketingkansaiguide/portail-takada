<?php

use App\Filament\Agency\Resources\AgencyFolderResource;
use App\Models\Folder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->agencyA = World::agency('Agence A');
    $this->agencyB = World::agency('Agence B');
    $this->userA = World::agencyUser($this->agencyA);
    $this->folderA = World::folder($this->agencyA);
    $this->folderB = World::folder($this->agencyB);
    $this->itemB = World::item($this->folderB, World::activityProduct());
});

it('limite la liste agence aux dossiers de sa propre agence', function () {
    Filament::setCurrentPanel(Filament::getPanel('agency'));
    $this->actingAs($this->userA, 'agency');

    $ids = AgencyFolderResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toBe([$this->folderA->id]);
});

it('applique les policies agence sur dossiers, prestations et suppression', function () {
    $draftA = $this->folderA;
    $confirmedA = World::folder($this->agencyA, ['status' => 'confirmed']);

    expect($this->userA->can('view', $draftA))->toBeTrue()
        ->and($this->userA->can('update', $draftA))->toBeTrue()
        ->and($this->userA->can('delete', $draftA))->toBeTrue()
        ->and($this->userA->can('delete', $confirmedA))->toBeFalse()
        ->and($this->userA->can('view', $this->folderB))->toBeFalse()
        ->and($this->userA->can('update', $this->folderB))->toBeFalse()
        ->and($this->userA->can('view', $this->itemB))->toBeFalse()
        ->and($this->userA->can('update', $this->itemB))->toBeFalse();
});

it("refuse l'édition directe par URL d'un dossier d'une autre agence", function () {
    $this->actingAs($this->userA, 'agency');

    $url = AgencyFolderResource::getUrl('edit', ['record' => $this->folderB], panel: 'agency');

    expect($this->get($url)->status())->toBeIn([403, 404]);
});

it("refuse les fichiers d'un dossier d'une autre agence", function () {
    $this->actingAs($this->userA, 'agency');

    $this->get(route('folders.drive-file', ['folder' => $this->folderB, 'fileId' => 'abc123']))->assertForbidden();
    $this->get(route('folders.legacy-file', ['folder' => $this->folderB, 'path' => 'folders/x.pdf']))->assertForbidden();
});

it('réserve les PDF internes au back-office', function () {
    $this->actingAs($this->userA, 'agency');

    $this->get(route('pdf.recapitulatif', $this->folderA))->assertForbidden();
    $this->get(route('pdf.fax', $this->itemB))->assertForbidden();
    $this->get(route('pdf.labels', ['ids' => $this->folderB->id]))->assertForbidden();
});

it("refuse le panel admin à un compte agence et le panel agence au staff", function () {
    $staff = World::staff('super_admin');

    expect($this->userA->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($this->userA->canAccessPanel(Filament::getPanel('agency')))->toBeTrue()
        ->and($staff->canAccessPanel(Filament::getPanel('agency')))->toBeFalse()
        ->and($staff->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('laisse le staff autorisé ouvrir le PDF récapitulatif', function () {
    $this->actingAs(World::staff('super_admin'), 'web');

    $this->get(route('pdf.recapitulatif', $this->folderA))->assertOk();
});

it("n'expose pas les prestations internes masquées dans la relation agence", function () {
    World::item($this->folderA, World::activityProduct(), ['is_internal' => true, 'title' => 'Frais interne']);
    World::item($this->folderA, World::activityProduct());

    expect($this->folderA->folderItems()->count())->toBe(1)
        ->and($this->folderA->allFolderItems()->count())->toBe(2);
});
