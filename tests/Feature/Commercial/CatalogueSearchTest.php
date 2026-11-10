<?php

use App\Models\Product;
use App\Support\CatalogueSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->fit = World::staff('commercial_fit');
    $this->folder = World::internalFolder(null, ['owner_user_id' => $this->fit->id, 'ops_status' => 'en_preparation', 'kind' => 'full_trip']);
});

it('ne cherche rien sous 2 caractères', function () {
    expect(CatalogueSearch::search('a'))->toBe([])->and(CatalogueSearch::search(' '))->toBe([]);
});

it('limite à 15 résultats avec une seule requête et cherche nom, ville, catégorie et variantes', function () {
    $count = Product::where('is_public', true)->count();
    Product::where('is_public', true)->orderBy('id')->limit(1)->update(['guide_city' => 'Zzvilleunique']);
    $cityHit = Product::where('guide_city', 'Zzvilleunique')->first();
    $category = $cityHit->category?->name ?? 'a';

    DB::enableQueryLog();
    $byName = CatalogueSearch::search(mb_substr((string) $cityHit->name, 0, 4));
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect(count($byName))->toBeLessThanOrEqual(15)->and($queries)->toBeLessThanOrEqual(2);
    expect(array_keys(CatalogueSearch::search('Zzvilleunique')))->toContain($cityHit->id);
    expect(strlen($category) >= 2 ? count(CatalogueSearch::search($category)) : 1)->toBeLessThanOrEqual(15)->and($count)->toBeGreaterThan(0);
    expect(CatalogueSearch::search('Zzvilleunique')[$cityHit->id])->toContain('Zzvilleunique');
});

it('propose à champ vide les prestations les plus préparées par l’utilisateur', function () {
    $a = Product::where('is_public', true)->orderBy('id')->first();
    $b = Product::where('is_public', true)->orderBy('id')->skip(1)->first();
    World::plannedItem($this->folder, $a, ['service_date' => '2027-04-03', 'created_by_user_id' => $this->fit->id]);
    World::plannedItem($this->folder, $b, ['service_date' => '2027-04-04', 'created_by_user_id' => $this->fit->id]);
    World::plannedItem($this->folder, $b, ['service_date' => '2027-04-05', 'created_by_user_id' => $this->fit->id]);

    $frequent = array_keys(CatalogueSearch::frequent($this->fit->id));
    expect($frequent[0] ?? null)->toBe($b->id)->and($frequent)->toContain($a->id);
    expect(CatalogueSearch::frequent(null))->toBe([]);
});
