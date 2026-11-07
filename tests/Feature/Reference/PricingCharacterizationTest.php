<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Catalogue;

uses(RefreshDatabase::class);

/*
 * Test de caractérisation du moteur tarifaire (lot 1).
 * Les valeurs attendues sont celles produites par le code au 2026-11-07 sur le
 * catalogue de production. Toute différence signale un changement de prix pour
 * les agences et doit être expliquée avant d'être acceptée.
 * Régénération volontaire : UPDATE_PRICE_SNAPSHOT=1 php artisan test --filter=PricingCharacterization
 */
it('produit exactement les prix de référence sur tout le catalogue de production', function () {
    Catalogue::load();

    $results = [];
    foreach (Catalogue::scenarios() as $key => $state) {
        $results[$key] = Catalogue::price($state);
    }

    if (getenv('UPDATE_PRICE_SNAPSHOT') === '1') {
        file_put_contents(Catalogue::SNAPSHOT, json_encode($results, JSON_PRETTY_PRINT));
        $this->markTestSkipped('Instantané des prix régénéré : ' . count($results) . ' scénarios.');
    }

    $expected = json_decode(file_get_contents(Catalogue::SNAPSHOT), true, flags: JSON_THROW_ON_ERROR);

    expect(array_keys($results))->toEqual(array_keys($expected));

    $differences = [];
    foreach ($expected as $key => $values) {
        if ($results[$key] != $values) {
            $differences[$key] = ['attendu' => $values, 'obtenu' => $results[$key]];
        }
    }

    expect($differences)->toBe([], 'Prix modifiés : ' . json_encode(array_slice($differences, 0, 5, true), JSON_PRETTY_PRINT));
});

it('couvre les familles de produits qui ont une règle de prix particulière', function () {
    Catalogue::load();

    $products = Product::with('productOptions')->get();

    expect($products->count())->toBe(108)
        ->and($products->where('product_type', 'transport')->count())->toBeGreaterThan(0)
        ->and($products->filter->usesAgeSelector()->count())->toBeGreaterThan(0)
        ->and($products->filter->usesMultiplier()->count())->toBeGreaterThan(0)
        ->and($products->where('category_id', 4)->count())->toBeGreaterThan(0)
        ->and($products->where('category_id', 3)->count())->toBeGreaterThan(0)
        ->and(\Illuminate\Support\Facades\DB::table('client_group_product')->count())->toBeGreaterThan(0);
});
