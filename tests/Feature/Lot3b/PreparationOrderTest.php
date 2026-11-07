<?php

use App\Jobs\CancelGuideMission;
use App\Jobs\SyncFolderItemCalendar;
use App\Jobs\SyncGuideMission;
use App\Models\Folder;
use App\Models\FolderItem;
use App\Models\FolderOrderBatch;
use App\Models\FolderPlannedItem;
use App\Models\FolderTask;
use App\Models\ItemStatus;
use App\Models\Product;
use App\Services\PlannedItemOrderService;
use App\Support\PlannedItemMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->commercial = World::staff('commercial_fit');
    $this->actingAs($this->commercial, 'web');
    $this->groupId = (int) DB::table('client_groups')->orderBy('id')->value('id');
    $this->folder = World::internalFolder(null, ['owner_user_id' => $this->commercial->id, 'sale_type' => 'b2c', 'pricing_client_group_id' => $this->groupId]);
    $this->service = app(PlannedItemOrderService::class);
    $this->products = Product::where('category_id', 1)->orderBy('id')->take(5)->get();
    $this->order = function (array $ids, ?string $key = null, bool $accept = false, ?int $lock = null) {
        $folder = $this->folder->fresh();

        return $this->service->order($folder, $ids, $this->commercial, $lock ?? (int) $folder->lock_version, $key ?? (string) Str::uuid(), $accept);
    };
});

function prepareFive($test): \Illuminate\Support\Collection
{
    return $test->products->map(fn ($p, $i) => World::plannedItem($test->folder, $p, ['service_date' => '2027-04-0' . ($i + 3)]));
}

it('1. prépare cinq prestations sans déclencher aucun automatisme', function () {
    Queue::fake();
    Mail::fake();

    $planned = prepareFive($this);

    expect($planned)->toHaveCount(5)
        ->and(FolderItem::where('folder_id', $this->folder->id)->count())->toBe(0)
        ->and($this->folder->fresh()->status)->toBe('draft')
        ->and((float) $this->folder->fresh()->total_price)->toBe(0.0)
        ->and(FolderTask::where('folder_id', $this->folder->id)->count())->toBe(0);
    Queue::assertNothingPushed();
    Mail::assertNothingSent();
});

it('2. commande deux prestations : seules ces deux passent en commande, avec traçabilité', function () {
    Queue::fake();
    $planned = prepareFive($this);
    $ids = [$planned[0]->id, $planned[2]->id];

    $result = ($this->order)($ids);

    expect($result['status'])->toBe('completed');
    $items = FolderItem::where('folder_id', $this->folder->id)->get();
    expect($items)->toHaveCount(2)
        ->and($items->pluck('planned_item_id')->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all())
        ->and($items->every(fn ($i) => $i->itemStatus->name === 'En attente de validation'))->toBeTrue()
        ->and($items->every(fn ($i) => $i->ordered_by_user_id === $this->commercial->id))->toBeTrue()
        ->and(FolderPlannedItem::whereIn('id', $ids)->pluck('state')->unique()->all())->toBe(['converted'])
        ->and(FolderPlannedItem::where('folder_id', $this->folder->id)->where('state', 'draft')->count())->toBe(3);

    $folder = $this->folder->fresh();
    expect($folder->status)->toBe('confirmed')->and($folder->ops_status)->toBe('reservations_en_cours');

    $batch = FolderOrderBatch::firstOrFail();
    expect($batch->user_id)->toBe($this->commercial->id)
        ->and($batch->snapshot)->toHaveCount(2)
        ->and(json_encode($batch->snapshot))->not->toContain('custom_values')
        ->and(json_encode($batch->snapshot))->not->toContain('Martin')
        ->and($folder->histories()->where('changes_payload', 'like', '%order_batch%')->exists())->toBeTrue();
    Queue::assertPushed(SyncFolderItemCalendar::class, 2);
});

it('3 et 4. modifie les prestations restantes sans toucher aux commandées ; une nouvelle prestation reste en préparation', function () {
    $planned = prepareFive($this);
    ($this->order)([$planned[0]->id, $planned[1]->id]);
    $ordered = FolderItem::where('folder_id', $this->folder->id)->get()->keyBy('planned_item_id');
    $before = $ordered->map->only(['quantity', 'total_price', 'service_date', 'updated_at'])->toArray();

    foreach ([2, 3, 4] as $i) {
        $planned[$i]->fresh()->update(['quantity' => 6, 'service_date' => '2027-05-01', 'total_price' => 123456]);
    }
    $added = World::plannedItem($this->folder, $this->products[0]);

    $after = FolderItem::where('folder_id', $this->folder->id)->get()->keyBy('planned_item_id')->map->only(['quantity', 'total_price', 'service_date', 'updated_at'])->toArray();
    expect($after)->toEqual($before)
        ->and($added->fresh()->state)->toBe('draft')
        ->and(FolderItem::where('folder_id', $this->folder->id)->count())->toBe(2);
});

it("5. changer l'avancement en « Réservations en cours » ne commande rien", function () {
    prepareFive($this);
    $this->folder->update(['ops_status' => 'reservations_en_cours']);

    expect(FolderItem::where('folder_id', $this->folder->id)->count())->toBe(0)
        ->and(FolderPlannedItem::where('folder_id', $this->folder->id)->where('state', 'draft')->count())->toBe(5)
        ->and($this->folder->fresh()->status)->toBe('draft');
});

it("6. retirer une prestation préparée ne déclenche aucune annulation fournisseur", function () {
    Queue::fake();
    $planned = World::plannedItem($this->folder, World::guideProduct());
    $planned->delete();

    Queue::assertNotPushed(CancelGuideMission::class);
    expect(FolderPlannedItem::find($planned->id))->toBeNull()
        ->and($this->folder->histories()->where('changes_payload', 'like', '%planned_deleted%')->exists())->toBeTrue();
});

it("7. annuler une prestation commandée applique les règles opérationnelles existantes", function () {
    Queue::fake();
    $planned = prepareFive($this);
    ($this->order)([$planned[0]->id, $planned[1]->id]);
    [$a, $b] = FolderItem::where('folder_id', $this->folder->id)->orderBy('id')->get()->all();
    $a->forceFill(['guide_mission_id' => 77])->saveQuietly();
    $a->update(['product_id' => World::guideProduct()->id]);

    $a->update(['item_status_id' => ItemStatus::where('name', 'Annulé')->value('id')]);

    Queue::assertPushed(CancelGuideMission::class, fn ($job) => $job->folderItemId === $a->id);
    expect((float) $this->folder->fresh()->total_price)->toBe((float) $b->fresh()->total_price)
        ->and($b->fresh()->itemStatus->name)->toBe('En attente de validation');
});

it("8. une commande répétée ne crée aucun doublon ; une clé réutilisée pour une autre sélection est refusée", function () {
    $planned = prepareFive($this);
    $key = (string) Str::uuid();

    $first = ($this->order)([$planned[0]->id], $key);
    $replay = ($this->order)([$planned[0]->id], $key);
    $other = ($this->order)([$planned[1]->id], $key);
    $again = ($this->order)([$planned[0]->id]); // nouvelle clé, même ligne déjà commandée

    expect($first['status'])->toBe('completed')
        ->and($replay['status'])->toBe('completed')->and($replay['replayed'] ?? false)->toBeTrue()
        ->and($other['status'])->toBe('rejected')
        ->and($again['status'])->toBe('rejected')
        ->and(FolderItem::where('folder_id', $this->folder->id)->count())->toBe(1)
        ->and(FolderOrderBatch::count())->toBe(1);
});

it("9. refuse une commande sur une version périmée du dossier, sans rien enregistrer", function () {
    $planned = prepareFive($this);
    $stale = (int) $this->folder->fresh()->lock_version;
    Folder::query()->whereKey($this->folder->id)->increment('lock_version'); // un collègue a enregistré

    $result = ($this->order)([$planned[0]->id, $planned[1]->id], null, false, $stale);

    expect($result['status'])->toBe('rejected')
        ->and(FolderItem::where('folder_id', $this->folder->id)->count())->toBe(0)
        ->and(FolderOrderBatch::count())->toBe(0);
});

it("9 bis. tout ou rien : une ligne invalide ou étrangère au dossier bloque toute la commande", function () {
    $planned = prepareFive($this);
    $other = World::internalFolder(null, ['sale_type' => 'b2c', 'pricing_client_group_id' => $this->groupId]);
    $foreign = World::plannedItem($other, $this->products[0]);
    $planned[1]->update(['service_date' => null]);

    $r1 = ($this->order)([$planned[0]->id, $foreign->id]);
    $r2 = ($this->order)([$planned[0]->id, $planned[1]->id]);

    expect($r1['status'])->toBe('rejected')->and($r2['status'])->toBe('rejected')
        ->and($r2['message'])->toContain('date manquante')
        ->and(FolderItem::count())->toBe(0);
});

it("10. le parcours B2B reste inchangé : pas de préparation sur un dossier agence", function () {
    $agency = World::agency('Agence A');
    $b2b = World::folder($agency);
    $item = World::item($b2b, World::activityProduct());

    expect(fn () => FolderPlannedItem::create(['folder_id' => $b2b->id, 'product_id' => $this->products[0]->id]))->toThrow(LogicException::class)
        ->and($item->exists)->toBeTrue()
        ->and($item->planned_item_id)->toBeNull();
});

it("11. missions guides : désactivées pour les dossiers internes par défaut, envoyées après validation si activées", function () {
    Queue::fake();
    $guide = World::plannedItem($this->folder, World::guideProduct());
    ($this->order)([$guide->id], null, true);
    Queue::assertNotPushed(SyncGuideMission::class);

    config(['services.guide_planning.internal_orders_enabled' => true]);
    $guide2 = World::plannedItem($this->folder, World::guideProduct(), ['service_date' => '2027-04-09']);
    ($this->order)([$guide2->id], null, true);
    Queue::assertPushed(SyncGuideMission::class, 1);
});

it("bloque toute création directe de prestation commandée sur un dossier interne (modèle et base)", function () {
    $planned = World::plannedItem($this->folder, $this->products[0]);

    expect(fn () => FolderItem::create(['folder_id' => $this->folder->id, 'product_id' => $this->products[0]->id, 'service_date' => '2027-04-05', 'quantity' => 1, 'unit_price' => 1, 'total_price' => 1]))
        ->toThrow(LogicException::class)
        ->and(fn () => FolderItem::create(['folder_id' => $this->folder->id, 'planned_item_id' => $planned->id, 'product_id' => $this->products[0]->id, 'quantity' => 1, 'unit_price' => 1, 'total_price' => 1]))
        ->toThrow(LogicException::class) // jeton absent : pas en cours de commande
        ->and(fn () => DB::table('folder_items')->insert(['folder_id' => $this->folder->id, 'product_id' => $this->products[0]->id, 'quantity' => 1, 'unit_price' => 1, 'total_price' => 1, 'purchase_unit_price' => 0, 'purchase_total_price' => 0, 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(\Illuminate\Database\QueryException::class)
        ->and(FolderItem::where('folder_id', $this->folder->id)->count())->toBe(0);
});

it("interdit en base de changer l'origine d'un dossier ou de déplacer une ligne commandée", function () {
    $planned = World::plannedItem($this->folder, $this->products[0]);
    ($this->order)([$planned->id]);
    $agency = World::agency('Agence B');
    $b2b = World::folder($agency);
    $item = FolderItem::where('folder_id', $this->folder->id)->firstOrFail();

    expect(fn () => DB::table('folders')->where('id', $this->folder->id)->update(['origin' => 'b2b']))->toThrow(\Illuminate\Database\QueryException::class)
        ->and(fn () => DB::table('folder_items')->where('id', $item->id)->update(['folder_id' => $b2b->id]))->toThrow(\Illuminate\Database\QueryException::class)
        ->and($this->folder->fresh()->origin)->toBe('internal');
});

it("refuse de commander sur un dossier annulé ou terminé, puis accepte après réouverture", function () {
    $planned = World::plannedItem($this->folder, $this->products[0]);

    $this->folder->update(['ops_status' => 'annule']);
    expect(($this->order)([$planned->id])['status'])->toBe('rejected');

    $this->folder->fresh()->update(['ops_status' => 'termine']);
    expect(($this->order)([$planned->id])['status'])->toBe('rejected');

    $this->folder->fresh()->update(['ops_status' => 'en_preparation']); // réouverture
    expect(($this->order)([$planned->id])['status'])->toBe('completed');
});

it("refuse de commander tant que la politique tarifaire n'est pas définie", function () {
    $this->folder->update(['pricing_client_group_id' => null]);
    $planned = World::plannedItem($this->folder, $this->products[0]);

    $result = ($this->order)([$planned->id]);
    expect($result['status'])->toBe('rejected')->and($result['message'])->toContain('politique tarifaire');
});

it("signale un tarif modifié depuis la préparation et n'applique le nouveau tarif qu'après acceptation", function () {
    $planned = World::plannedItem($this->folder, $this->products[0]);
    $correct = $planned->total_price;
    $planned->update(['total_price' => $correct + 5000]); // tarif préparé devenu différent du tarif actuel

    $summary = $this->service->summary($this->folder->fresh(), [$planned->id], $this->commercial);
    $refused = ($this->order)([$planned->id]);
    $accepted = ($this->order)([$planned->id], null, true);

    expect($summary['has_price_changes'])->toBeTrue()
        ->and($refused['status'])->toBe('rejected')
        ->and($accepted['status'])->toBe('completed')
        ->and(FolderItem::where('planned_item_id', $planned->id)->value('total_price'))->toBe($correct)
        ->and(FolderOrderBatch::first()->price_changes_acknowledged)->toBeTrue()
        ->and(FolderOrderBatch::first()->snapshot[0]['prepared']['total_price'])->toBe($correct + 5000);
});

it("refuse la commande à un profil sans droit", function () {
    $planned = World::plannedItem($this->folder, $this->products[0]);
    $direction = World::staff('direction');

    expect(fn () => $this->service->order($this->folder->fresh(), [$planned->id], $direction, (int) $this->folder->fresh()->lock_version, (string) Str::uuid(), false))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it("couvre toutes les colonnes réelles de folder_items dans la correspondance préparation → commande", function () {
    $columns = Schema::getColumnListing('folder_items');
    $mapped = array_merge(PlannedItemMapper::COPIED, PlannedItemMapper::SET_BY_ORDER, PlannedItemMapper::OPERATIONAL);
    sort($columns);
    sort($mapped);

    expect($columns)->toBe($mapped)
        ->and(array_diff(PlannedItemMapper::COPIED, Schema::getColumnListing('folder_planned_items')))->toBe([]);

    // Aller-retour : toutes les colonnes métier remplies sont retrouvées sur la prestation commandée.
    $planned = World::plannedItem($this->folder, $this->products[0], ['title' => 'Titre', 'pax_children' => 1, 'pax_teenagers' => 1, 'is_internal' => false]);
    ($this->order)([$planned->id]);
    $item = FolderItem::where('planned_item_id', $planned->id)->firstOrFail();
    foreach (PlannedItemMapper::COPIED as $column) {
        expect([$column => $item->getAttribute($column)])->toEqual([$column => $planned->fresh()->getAttribute($column)]);
    }
});

it("affiche la fiche interne avec préparation, commandes et historique (sans suppression des lignes commandées)", function () {
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    $planned = prepareFive($this);
    ($this->order)([$planned[0]->id]);

    \Livewire\Livewire::test(\App\Filament\Resources\Folders\Pages\EditFolder::class, ['record' => $this->folder->getRouteKey()])
        ->assertOk()
        ->assertSee(__('Prestations en préparation'))
        ->assertSee(__('Budget prévisionnel (préparation)'))
        ->assertSee(__('Historique des commandes'))
        ->assertSee(__('Commander les prestations sélectionnées'))
        ->assertDontSeeHtml('class="is-del"');
});

it("commande depuis l'interface (action Filament) uniquement les prestations cochées", function () {
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    $planned = prepareFive($this);

    \Livewire\Livewire::test(\App\Filament\Resources\Folders\Pages\EditFolder::class, ['record' => $this->folder->getRouteKey()])
        ->callAction('orderPlannedItems', data: ['planned_ids' => [$planned[1]->id, $planned[3]->id]])
        ->assertHasNoActionErrors();

    expect(FolderItem::where('folder_id', $this->folder->id)->pluck('planned_item_id')->sort()->values()->all())
        ->toBe([$planned[1]->id, $planned[3]->id]);
});
