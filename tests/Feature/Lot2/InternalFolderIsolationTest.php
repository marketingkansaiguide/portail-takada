<?php

use App\Filament\Agency\Pages\ViewProduct;
use App\Filament\Agency\Resources\AgencyFolderResource;
use App\Livewire\FolderChat;
use App\Models\Folder;
use App\Support\FolderAccess;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->agencyA = World::agency('Agence A');
    $this->userA = World::agencyUser($this->agencyA);
    $this->b2bFolder = World::folder($this->agencyA);
    // Dossier interne associé à l'agence A, pour information seulement.
    $this->internal = World::internalFolder($this->agencyA);
    $this->internalItem = World::item($this->internal, World::activityProduct());
});

it("n'affiche jamais un dossier interne dans la liste de l'agence associée", function () {
    Filament::setCurrentPanel(Filament::getPanel('agency'));
    $this->actingAs($this->userA, 'agency');

    expect(AgencyFolderResource::getEloquentQuery()->pluck('id')->all())->toBe([$this->b2bFolder->id])
        ->and(FolderAccess::scopeForAgencyId(Folder::query(), $this->agencyA->id)->pluck('id')->all())->toBe([$this->b2bFolder->id]);
});

it("refuse à l'agence associée toute action sur le dossier interne et ses prestations", function () {
    expect($this->userA->can('view', $this->internal))->toBeFalse()
        ->and($this->userA->can('update', $this->internal))->toBeFalse()
        ->and($this->userA->can('delete', $this->internal))->toBeFalse()
        ->and($this->userA->can('view', $this->internalItem))->toBeFalse()
        ->and($this->userA->can('update', $this->internalItem))->toBeFalse()
        ->and($this->userA->can('delete', $this->internalItem))->toBeFalse()
        ->and(AgencyFolderResource::canView($this->internal))->toBeFalse();
});

it("refuse l'accès direct par URL, fichiers et PDF au dossier interne", function () {
    $this->actingAs($this->userA, 'agency');

    expect($this->get(AgencyFolderResource::getUrl('edit', ['record' => $this->internal], panel: 'agency'))->status())->toBeIn([403, 404]);
    $this->get(route('folders.drive-file', ['folder' => $this->internal, 'fileId' => 'abc123']))->assertForbidden();
    $this->get(route('folders.legacy-file', ['folder' => $this->internal, 'path' => 'folders/x.pdf']))->assertForbidden();
    $this->get(route('pdf.recapitulatif', $this->internal))->assertForbidden();
    $this->get(route('pdf.fax', $this->internalItem))->assertForbidden();
});

it("refuse le chat du dossier interne à l'agence associée", function () {
    Filament::setCurrentPanel(Filament::getPanel('agency'));
    $this->actingAs($this->userA, 'agency');

    Livewire::test(FolderChat::class, ['folder' => $this->internal])->assertForbidden();
});

it("ne propose pas le dossier interne dans le catalogue agence (ajout de prestation)", function () {
    Filament::setCurrentPanel(Filament::getPanel('agency'));
    $this->actingAs($this->userA, 'agency');

    $page = new ViewProduct();
    $page->activeAgencyId = $this->agencyA->id;

    expect($page->foldersList()->pluck('id')->all())->toBe([$this->b2bFolder->id]);
});

it("refuse la création d'un dossier sans origine explicite", function () {
    Folder::create(['agency_id' => $this->agencyA->id, 'folder_name' => 'SANS ORIGINE']);
})->throws(LogicException::class, 'Origine du dossier manquante');

it("refuse un dossier interne portant une agence propriétaire ou un vendeur agence", function () {
    expect(fn () => World::internalFolder(null, ['agency_id' => $this->agencyA->id]))->toThrow(LogicException::class)
        ->and(fn () => World::internalFolder(null, ['main_seller_id' => $this->userA->id]))->toThrow(LogicException::class)
        ->and(fn () => $this->internal->update(['agency_id' => $this->agencyA->id]))->toThrow(LogicException::class);
});

it("interdit de changer l'origine d'un dossier existant", function () {
    expect(fn () => $this->b2bFolder->update(['origin' => Folder::ORIGIN_INTERNAL]))->toThrow(LogicException::class)
        ->and(fn () => $this->internal->update(['origin' => Folder::ORIGIN_B2B]))->toThrow(LogicException::class)
        ->and($this->b2bFolder->fresh()->origin)->toBe('b2b');
});

it("laisse une agence associée sans compte et sans aucun accès", function () {
    $agencyWithoutAccount = World::agency('Agence sans compte');
    World::internalFolder($agencyWithoutAccount);

    expect($agencyWithoutAccount->users()->count())->toBe(0);
});

it("conserve le comportement des dossiers B2B créés pour une agence", function () {
    expect($this->userA->can('view', $this->b2bFolder))->toBeTrue()
        ->and($this->userA->can('update', $this->b2bFolder))->toBeTrue()
        ->and($this->userA->can('delete', $this->b2bFolder))->toBeTrue();
});
