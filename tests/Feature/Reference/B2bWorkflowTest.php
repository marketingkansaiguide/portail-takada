<?php

use App\Filament\Agency\Resources\AgencyFolderResource\Pages\EditAgencyFolder;
use App\Jobs\CancelGuideMission;
use App\Jobs\SyncGuideMission;
use App\Mail\FolderValidatedMail;
use App\Models\Folder;
use App\Models\FolderItem;
use App\Models\FolderTask;
use App\Models\ItemStatus;
use App\Services\ActionBoardTaskGenerator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->agency = World::agency('Agence A');
    $this->seller = World::agencyUser($this->agency);
    World::staff('super_admin');
});

it("attribue une référence unique et le segment billetterie à un dossier créé par une agence", function () {
    $this->actingAs($this->seller, 'agency');
    $folder = World::folder($this->agency);

    expect($folder->reference)->toMatch('/^TKD-\d{6}-[A-Z0-9]{4}$/')
        ->and($folder->segment)->toBe('billetterie')
        ->and($folder->histories()->count())->toBe(1);
});

it('attribue le statut « En attente de validation » à toute nouvelle prestation et recalcule le total', function () {
    $folder = World::folder($this->agency, ['folder_fee' => 1500]);
    $item = World::item($folder, World::activityProduct(), ['total_price' => 20000]);

    expect($item->fresh()->itemStatus->name)->toBe('En attente de validation')
        ->and((float) $folder->fresh()->total_price)->toBe(21500.0);
});

it("exclut les prestations annulées du total facturable", function () {
    $folder = World::folder($this->agency);
    $kept = World::item($folder, World::activityProduct(), ['total_price' => 20000]);
    $cancelled = World::item($folder, World::activityProduct(), ['total_price' => 9000]);
    $cancelled->update(['item_status_id' => ItemStatus::where('name', 'Annulé')->value('id')]);

    expect((float) $folder->fresh()->total_price)->toBe(20000.0);
});

it("valide un dossier agence : confirmation, email à l'équipe, tâche et missions guides", function () {
    Mail::fake();
    Queue::fake();
    \App\Models\Setting::query()->delete();

    $folder = World::folder($this->agency);
    $guideItem = World::item($folder, World::guideProduct());
    World::item($folder, World::activityProduct());

    Filament::setCurrentPanel(Filament::getPanel('agency'));
    $this->actingAs($this->seller, 'agency');

    Livewire::test(EditAgencyFolder::class, ['record' => $folder->getRouteKey()])
        ->callAction('validateFolder', data: ['accept_conditions' => true, 'accept_fee' => true])
        ->assertHasNoActionErrors();

    expect($folder->fresh()->status)->toBe('confirmed')
        ->and(FolderTask::where('action_code', "new_folder_confirmed_{$folder->id}")->exists())->toBeTrue();
    Mail::assertSent(FolderValidatedMail::class);
    Queue::assertPushed(SyncGuideMission::class, fn ($job) => $job->folderItemId === $guideItem->id);
    Queue::assertPushed(SyncGuideMission::class, 1);
});

it("met en file une mission guide pour une prestation guide ajoutée à un dossier confirmé, et l'annule à l'annulation du dossier", function () {
    Queue::fake();
    $folder = World::folder($this->agency, ['status' => 'confirmed']);
    $item = World::item($folder, World::guideProduct());

    Queue::assertPushed(SyncGuideMission::class, fn ($job) => $job->folderItemId === $item->id);

    $item->forceFill(['guide_mission_id' => 999])->saveQuietly();
    $folder->update(['status' => 'cancelled']);

    Queue::assertPushed(CancelGuideMission::class, fn ($job) => $job->folderItemId === $item->id);
});

it("ne met aucune mission guide en file tant que le dossier est en brouillon", function () {
    Queue::fake();
    $folder = World::folder($this->agency);
    World::item($folder, World::guideProduct());

    Queue::assertNotPushed(SyncGuideMission::class);
});

it('annule la mission guide quand une prestation guide passe en annulé', function () {
    Queue::fake();
    $folder = World::folder($this->agency, ['status' => 'confirmed']);
    $item = World::item($folder, World::guideProduct());
    $item->forceFill(['guide_mission_id' => 77])->saveQuietly();

    $item->update(['item_status_id' => ItemStatus::where('name', 'Annulé')->value('id')]);

    Queue::assertPushed(CancelGuideMission::class, fn ($job) => $job->folderItemId === $item->id);
});

it('clôture automatiquement les dossiers dont le voyage est terminé', function () {
    $past = World::folder($this->agency, ['status' => 'confirmed', 'end_date' => now()->subDays(2)->toDateString()]);
    $draftPast = World::folder($this->agency, ['status' => 'draft', 'end_date' => now()->subDays(2)->toDateString()]);
    $future = World::folder($this->agency, ['status' => 'confirmed', 'end_date' => now()->addDays(5)->toDateString()]);
    $cancelled = World::folder($this->agency, ['status' => 'cancelled', 'end_date' => now()->subDays(2)->toDateString()]);

    $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($event) => $event->command === null && $event->expression === '0 0 * * *');
    $event->run(app());

    expect($past->fresh()->status)->toBe('completed')
        ->and($draftPast->fresh()->status)->toBe('completed')
        ->and($future->fresh()->status)->toBe('confirmed')
        ->and($cancelled->fresh()->status)->toBe('cancelled');
});

it("génère la tâche « nouveau dossier » pour un brouillon et la clôt une fois confirmé", function () {
    \Illuminate\Support\Facades\Mail::fake();
    $folder = World::folder($this->agency);

    app(ActionBoardTaskGenerator::class)->generate();
    expect(FolderTask::where('action_code', "new_folder_{$folder->id}")->where('is_completed', false)->exists())->toBeTrue();

    $folder->update(['status' => 'confirmed']);
    app(ActionBoardTaskGenerator::class)->generate();
    expect(FolderTask::where('action_code', "new_folder_{$folder->id}")->where('is_completed', false)->exists())->toBeFalse();
});

it("crée côté admin un dossier pour une agence, visible par cette agence", function () {
    $this->actingAs(World::staff('super_admin'), 'web');
    $folder = World::folder($this->agency);

    expect($this->seller->can('view', $folder))->toBeTrue()
        ->and($this->seller->can('update', $folder))->toBeTrue();
});
