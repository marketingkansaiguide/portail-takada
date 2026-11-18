<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\TransportFare;
use App\Models\TransportFareSeason;
use App\Support\TransportFareResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fareOptions(): array
{
    $category = Category::forceCreate(['name' => 'Transport']);
    $product = Product::forceCreate(['name' => 'Ticket de train / bus', 'product_type' => 'transport', 'category_id' => $category->id]);

    return [
        'reserved' => ProductOption::create(['product_id' => $product->id, 'name' => 'Place réservée']),
        'green' => ProductOption::create(['product_id' => $product->id, 'name' => 'Green']),
    ];
}

function fareRoute(array $o = []): array
{
    return $o + ['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station', 'departure_date' => '2027-03-10', 'pax_count' => 2, 'departure_time' => ''];
}

test('le prix de la saison est trouvé dans les deux sens et multiplié par les passagers', function () {
    ['reserved' => $r] = fareOptions();
    TransportFare::create(['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station', 'product_option_id' => $r->id,
        'price_low' => 13970, 'price_normal' => 14170, 'price_high' => 14370, 'price_peak' => 14570]);
    TransportFareSeason::create(['season' => 'peak', 'starts_on' => '2027-04-27', 'ends_on' => '2027-05-06']);

    $normal = (new TransportFareResolver())->forRoute(fareRoute(['option_id' => $r->id]));
    expect($normal['unit_price'])->toBe(14170)->and($normal['total'])->toBe(28340)->and($normal['season'])->toBe('normal')
        ->and($normal['is_max_reference'])->toBeFalse()->and($normal['child_price'])->toBe(7080);

    // Sens inverse, nom saisi sans « Station », en période de pointe.
    $peak = (new TransportFareResolver())->forRoute(fareRoute(['option_id' => $r->id, 'departure_station' => 'kyoto', 'arrival_station' => 'TOKYO', 'departure_date' => '2027-05-01']));
    expect($peak['unit_price'])->toBe(14570)->and($peak['season'])->toBe('peak');
});

test('le train saisi choisit sa ligne, sinon le train le plus cher sert de référence', function () {
    ['reserved' => $r] = fareOptions();
    TransportFare::create(['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station', 'train_name' => 'Nozomi', 'product_option_id' => $r->id, 'price_normal' => 14170]);
    TransportFare::create(['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station', 'train_name' => 'Hikari', 'product_option_id' => $r->id, 'price_normal' => 13850]);

    $hikari = (new TransportFareResolver())->forRoute(fareRoute(['option_id' => $r->id, 'departure_time' => '09:30, Hikari 502']));
    expect($hikari['unit_price'])->toBe(13850)->and($hikari['is_max_reference'])->toBeFalse()->and($hikari['train_name'])->toBe('Hikari');

    $nozomi = (new TransportFareResolver())->forRoute(fareRoute(['option_id' => $r->id, 'departure_time' => 'NOZOMI 243']));
    expect($nozomi['unit_price'])->toBe(14170);

    $unknown = (new TransportFareResolver())->forRoute(fareRoute(['option_id' => $r->id, 'departure_time' => '10:00']));
    expect($unknown['unit_price'])->toBe(14170)->and($unknown['is_max_reference'])->toBeTrue()->and($unknown['train_name'])->toBe('Nozomi');
});

test('sans tarif pour la classe, la saison ou la période de validité, le trajet reste sur devis', function () {
    ['reserved' => $r, 'green' => $g] = fareOptions();
    TransportFare::create(['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station', 'product_option_id' => $r->id,
        'price_normal' => 14170, 'valid_to' => '2027-12-31']);
    TransportFareSeason::create(['season' => 'high', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-31']);

    $resolver = new TransportFareResolver();
    expect($resolver->forRoute(fareRoute(['option_id' => $g->id])))->toBeNull()               // classe absente
        ->and($resolver->forRoute(fareRoute(['option_id' => $r->id])))->toBeNull()            // saison haute non remplie
        ->and($resolver->forRoute(fareRoute(['option_id' => $r->id, 'departure_date' => '2028-01-10'])))->toBeNull() // hors validité
        ->and($resolver->forRoute(fareRoute(['option_id' => $r->id, 'departure_date' => '2027-06-10']))['unit_price'])->toBe(14170)
        ->and($resolver->forRoute(fareRoute(['option_id' => $r->id, 'arrival_station' => 'Nara Station'])))->toBeNull();
});

test('prix unique, ligne inactive et sens unique', function () {
    ['reserved' => $r] = fareOptions();
    TransportFare::create(['departure_station' => 'Namba Station', 'arrival_station' => 'Kansai-Airport Station', 'product_option_id' => $r->id,
        'pricing_mode' => 'flat', 'price_flat' => 1560, 'both_directions' => false]);
    TransportFare::create(['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station', 'product_option_id' => $r->id, 'price_normal' => 1, 'is_active' => false]);

    $all = (new TransportFareResolver())->forRoutes([
        fareRoute(['option_id' => $r->id, 'departure_station' => 'Namba Station', 'arrival_station' => 'Kansai-Airport Station', 'pax_count' => 3]),
        fareRoute(['option_id' => $r->id, 'departure_station' => 'Kansai-Airport Station', 'arrival_station' => 'Namba Station']),
        fareRoute(['option_id' => $r->id]),
    ]);
    expect($all['priced'])->toBe(1)->and($all['count'])->toBe(3)->and($all['total'])->toBe(4680)
        ->and($all['routes'][1])->toBeNull()->and($all['routes'][2])->toBeNull();
});

test('enfants au demi-tarif, jeunes enfants gratuits dans la limite de 2 par payant, bébés gratuits', function () {
    ['reserved' => $r] = fareOptions();
    TransportFare::create(['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station', 'product_option_id' => $r->id, 'price_normal' => 14170]);
    $resolver = new TransportFareResolver();

    // 2 adultes + 1 enfant 8 ans + 1 enfant 3 ans sans place + 1 bébé.
    $family = $resolver->forRoute(fareRoute(['option_id' => $r->id, 'pax_count' => 5, 'children_count' => 1, 'young_children_count' => 1, 'infants_count' => 1]));
    expect($family['adults'])->toBe(2)->and($family['children'])->toBe(1)->and($family['free'])->toBe(2)
        ->and($family['total'])->toBe(2 * 14170 + 7080);

    // 1 adulte + 3 jeunes enfants : le 3e paie le tarif enfant.
    $three = $resolver->forRoute(fareRoute(['option_id' => $r->id, 'pax_count' => 4, 'young_children_count' => 3]));
    expect($three['children'])->toBe(1)->and($three['free'])->toBe(2)->and($three['total'])->toBe(14170 + 7080);

    // Jeune enfant seul : tarif enfant.
    $alone = TransportFareResolver::passengers(['pax_count' => 1, 'young_children_count' => 1]);
    expect($alone)->toBe(['total' => 1, 'adults' => 0, 'children_paying' => 1, 'free' => 0]);

    // Prix enfant saisi dans la grille prioritaire sur le demi-tarif.
    TransportFare::query()->update(['child_price' => 7000]);
    expect((new TransportFareResolver())->forRoute(fareRoute(['option_id' => $r->id, 'pax_count' => 1, 'children_count' => 1]))['total'])->toBe(7000);
});

it('chiffre plusieurs trajets en peu de requêtes SQL (tarifs par paire de gares et saisons chargés une seule fois)', function () {
    ['reserved' => $opt] = fareOptions();
    foreach ([['Tokyo Station', 'Kyoto Station'], ['Kyoto Station', 'Osaka Station'], ['Osaka Station', 'Nara Station']] as [$from, $to]) {
        foreach ([null, 'Nozomi'] as $train) {
            TransportFare::create(['departure_station' => $from, 'arrival_station' => $to, 'train_name' => $train, 'product_option_id' => $opt->id,
                'season_calendar' => 'A', 'price_low' => 1000, 'price_normal' => 1100, 'price_high' => 1200, 'price_peak' => 1300]);
        }
    }
    \App\Models\TransportFareSeason::create(['company' => 'A', 'season' => 'high', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-30']);

    $route = fn (string $from, string $to) => ['departure_station' => $from, 'arrival_station' => $to, 'departure_date' => '2027-06-10',
        'option_id' => $opt->id, 'pax_count' => 2];
    $routes = [$route('Tokyo Station', 'Kyoto Station'), $route('Kyoto Station', 'Osaka Station'), $route('Osaka Station', 'Nara Station')];

    $resolver = new TransportFareResolver;
    DB::flushQueryLog();
    DB::enableQueryLog();
    $result = $resolver->forRoutes($routes);
    $resolver->adjustRoutes($routes);
    foreach ($routes as $r) {
        $resolver->availableOptions($r);
    }
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($result['priced'])->toBe(3)
        ->and($result['routes'][0]['season'])->toBe('high')
        ->and($count)->toBeLessThanOrEqual(4); // 3 paires de gares + 1 calendrier de saisons
});
