<?php

use App\Jobs\SyncFolderItemCalendar;
use App\Jobs\SyncGuideMission;
use App\Models\FolderItem;
use App\Services\FolderItemCalendar;
use App\Services\GuideMissionPayloadFactory;
use App\Services\GuidePlanningApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->actingAs(World::staff('super_admin'), 'web');
});

it("suit l'échec d'une synchronisation Calendar et la relance sans créer de doublon", function () {
    $folder = World::folder(World::agency('Agence A'), ['status' => 'confirmed']);
    $calls = 0;
    app()->instance(FolderItemCalendar::class, new class($calls) extends FolderItemCalendar {
        public function __construct(public int &$calls) {}
        public function sync(FolderItem $item): ?string
        {
            $this->calls++;

            return $this->calls === 1 ? 'Google indisponible (503)' : null;
        }
    });

    Queue::fake();
    $item = World::item($folder, World::activityProduct());
    Queue::assertPushed(SyncFolderItemCalendar::class, 1);

    // Premier passage : échec enregistré, le job lève une exception pour être retenté.
    expect(fn () => (new SyncFolderItemCalendar($item->id))->handle())->toThrow(RuntimeException::class);
    expect($item->fresh()->calendar_sync_status)->toBe('failed')
        ->and($item->fresh()->calendar_sync_error)->toContain('503');

    // Relance : commande dédiée, puis succès.
    $this->artisan('calendar:retry-sync')->assertSuccessful();
    Queue::assertPushed(SyncFolderItemCalendar::class, 2);
    (new SyncFolderItemCalendar($item->id))->handle();
    expect($item->fresh()->calendar_sync_status)->toBe('synced')
        ->and($item->fresh()->calendar_sync_error)->toBeNull()
        ->and($calls)->toBe(2);
});

it("rejoue une mission guide avec la même référence externe si l'enregistrement local échoue après la création distante", function () {
    config(['services.guide_planning.base_url' => 'https://guides.test/wp-json/pm/v1', 'services.guide_planning.api_key' => 'cle-test']);
    $folder = World::folder(World::agency('Agence A'), ['status' => 'confirmed']);
    Queue::fake();
    $product = \App\Models\Product::where('category_id', 4)->whereNotNull('guide_city')->whereNotNull('guide_start_time')->orderBy('id')->firstOrFail();
    $item = World::item($folder, $product, ['purchase_total_price' => 18000]);

    Http::fake(['guides.test/*' => Http::response(['success' => true, 'data' => ['id' => 501]], 200)]);

    // Panne simulée : la mission est créée côté WordPress, puis l'écriture locale échoue.
    // Toutes les écritures locales échouent pendant cette première tentative.
    $databaseDown = false;
    FolderItem::updating(function (FolderItem $model) use (&$databaseDown) {
        if ($databaseDown && $model->isDirty('guide_mission_id')) {
            throw new RuntimeException('Base indisponible pendant l’enregistrement');
        }
    });

    try {
        $job = new SyncGuideMission($item->id);
        $databaseDown = false;
        Http::fake(function (Request $request) use (&$databaseDown) {
            static $calls = 0;
            $databaseDown = (++$calls === 1); // la base tombe juste après la 1re réponse de WordPress

            return Http::response(['success' => true, 'data' => ['id' => 501]], 200);
        });
        app()->call([$job, 'handle']);
    } catch (\Throwable) {
        // le job sera retenté par la file
    }
    $databaseDown = false;
    expect($item->fresh()->guide_mission_id)->toBeNull();

    app()->call([new SyncGuideMission($item->id), 'handle']); // nouvelle tentative

    $posts = collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn (Request $r) => $r->method() === 'POST')->values();
    expect($posts)->toHaveCount(2)
        ->and($posts[0]['external_source'])->toBe($posts[1]['external_source'])
        ->and($posts[0]['external_reference'])->toBe('folder_item_' . $item->id)
        ->and($posts[1]['external_reference'])->toBe($posts[0]['external_reference'])
        ->and($item->fresh()->guide_mission_id)->toBe(501)
        ->and(FolderItem::where('folder_id', $folder->id)->count())->toBe(1);
})->skip(fn () => ! method_exists(GuidePlanningApi::class, 'syncMission'), 'API guides absente');

it("renseigne l'agence obligatoire de Planning Guides pour une mission de dossier interne", function () {
    $product = \App\Models\Product::where('category_id', 4)->whereNotNull('guide_city')->whereNotNull('guide_start_time')->orderBy('id')->firstOrFail();
    $agency = World::agency('Voyages Lumière');
    $withAgency = World::internalFolder($agency);
    $without = World::internalFolder();

    $p1 = app(GuideMissionPayloadFactory::class)->make(World::orderedInternalItem($withAgency, $product)->fresh(['folder.clientAgency', 'product', 'productOption']));
    $p2 = app(GuideMissionPayloadFactory::class)->make(World::orderedInternalItem($without, $product)->fresh(['folder.clientAgency', 'product', 'productOption']));

    expect($p1['agency'])->toBe('Voyages Lumière')
        ->and($p2['agency'])->toBe('Takada Travel (dossier interne)')
        ->and($p1['external_reference'])->not->toBe($p2['external_reference']);
});
