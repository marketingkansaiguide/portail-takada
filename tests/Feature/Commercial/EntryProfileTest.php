<?php

use App\Models\FolderItem;
use App\Models\Product;
use App\Support\EntryProfile;
use App\Support\TripTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->fit = World::staff('commercial_fit');
    $this->folder = World::internalFolder(null, ['start_date' => '2026-12-17', 'end_date' => '2027-01-03', 'owner_user_id' => $this->fit->id, 'ops_status' => 'en_preparation', 'kind' => 'full_trip']);
});

it('déduit le profil de saisie du produit, avec surcharge possible', function () {
    $p = new Product(['name' => 'Accueil aéroport long + guidage']);
    expect(EntryProfile::for($p))->toBe('airport_welcome')
        ->and(EntryProfile::for(new Product(['name' => 'Ticket de train / bus'])))->toBe('train_bus')
        ->and(EntryProfile::for(new Product(['name' => 'Hakone freepass'])))->toBe('rental')
        ->and(EntryProfile::for(new Product(['name' => 'Visite guidée journée', 'entry_profile' => 'transfer'])))->toBe('transfer')
        ->and(EntryProfile::for(null))->toBe('other');
});

it('n’affiche que les champs du profil', function () {
    expect(EntryProfile::fields('visit'))->toBe(['meeting_time', 'meeting_point'])
        ->and(EntryProfile::fields('train_bus'))->toContain('route_from', 'route_to', 'end_time')->not->toContain('meeting_point')
        ->and(EntryProfile::fields('rental'))->toContain('end_date');
});

it('copie trajet, heure d’arrivée et date de fin à la commande et libelle « Train A → B »', function () {
    $planned = World::plannedItem($this->folder, World::activityProduct(), [
        'route_from' => 'Kanazawa', 'route_to' => 'Takayama', 'meeting_time' => '09:20', 'end_time' => '11:52', 'end_date' => '2026-12-22',
    ]);
    $user = \App\Models\User::where('role', 'super_admin')->first() ?? World::staff('super_admin');
    $folder = $this->folder->fresh();
    app(\App\Services\PlannedItemOrderService::class)->order($folder, [$planned->id], $user, (int) $folder->lock_version, (string) \Illuminate\Support\Str::uuid(), true);

    $ordered = FolderItem::where('planned_item_id', $planned->id)->firstOrFail();
    expect($ordered->route_from)->toBe('Kanazawa')->and($ordered->route_to)->toBe('Takayama')
        ->and($ordered->end_time)->toBe('11:52')->and($ordered->end_date->format('Y-m-d'))->toBe('2026-12-22');

    expect(EntryProfile::routeLabel('Ticket de train / bus', 'Kanazawa', 'Takayama'))->toBe('Train Kanazawa → Takayama');
    $labels = collect(TripTimeline::build($this->folder->fresh())['days'])->flatMap(fn ($d) => collect($d['entries'])->pluck('label'))->implode(' | ');
    expect($labels)->toContain('Kanazawa → Takayama')->toContain('09:20 → 11:52');
});
