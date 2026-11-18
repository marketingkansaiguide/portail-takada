<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\TransportFare;
use App\Support\TransportFareResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Ids réels du produit « Ticket de train / bus » : 23 non réservée, 24 réservée, 25 Green, 26 Gran Class.
function classProduct(): Product
{
    $category = Category::forceCreate(['name' => 'Transport']);
    $product = Product::forceCreate(['name' => 'Ticket de train / bus', 'product_type' => 'transport', 'category_id' => $category->id]);
    foreach ([23 => 'Place non réservée', 24 => 'Place réservée', 25 => 'Green', 26 => 'Gran Class'] as $id => $name) {
        ProductOption::forceCreate(['id' => $id, 'product_id' => $product->id, 'name' => $name]);
    }

    return $product;
}

function classFare(int $optionId, array $o = []): TransportFare
{
    return TransportFare::create($o + ['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station', 'product_option_id' => $optionId, 'price_normal' => 14170]);
}

function classRoute(array $o = []): array
{
    return $o + ['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station', 'departure_date' => '2027-03-10', 'pax_count' => 1, 'departure_time' => '', 'option_id' => 24];
}

test('seule la réservée est en grille : Green et Gran Class sont grisées, un trajet en Green passe en réservée avec la note', function () {
    classProduct();
    classFare(24);
    $r = new TransportFareResolver();

    expect($r->availableOptions(classRoute()))->toBe([24])
        ->and($r->fallbackOption(classRoute(['option_id' => 25])))->toBe(24)
        ->and($r->fallbackOption(classRoute(['option_id' => 26])))->toBe(24)
        ->and($r->fallbackOption(classRoute(['option_id' => 24])))->toBeNull();

    $adjusted = $r->adjustRoutes([classRoute(['option_id' => 25])], [24 => 'Place réservée']);
    expect($adjusted[0]['option_id'])->toBe(24)
        ->and($adjusted[0]['option_switch_note'])->toBe('Classe indisponible sur ce trajet, passée en place réservée.');
});

test('Gran Class est grisée sur tous les trajets connus de la grille', function () {
    classProduct();
    classFare(24);
    classFare(25, ['arrival_station' => 'Nara Station']);
    classFare(24, ['arrival_station' => 'Nara Station']);
    $r = new TransportFareResolver();

    foreach (['Kyoto Station', 'Nara Station'] as $to) {
        expect($r->availableOptions(classRoute(['arrival_station' => $to])))->not->toContain(26);
        expect($r->fallbackOption(classRoute(['arrival_station' => $to, 'option_id' => 26])))->toBe(24);
    }
});

test('trajet hors grille : rien n\'est grisé et aucune bascule', function () {
    classProduct();
    classFare(24);
    $r = new TransportFareResolver();
    $route = classRoute(['arrival_station' => 'Hiroshima Station', 'option_id' => 26]);

    expect($r->availableOptions($route))->toBeNull()
        ->and($r->fallbackOption($route))->toBeNull()
        ->and($r->forRoute($route))->toBeNull()
        ->and($r->availableOptions(classRoute(['departure_date' => '2030-01-01'] )))->toBe([24]);
});

test('trajet hors validité ou tarif inactif : traité comme hors grille', function () {
    classProduct();
    classFare(24, ['valid_to' => '2026-12-31']);
    classFare(25, ['is_active' => false]);
    $r = new TransportFareResolver();

    expect($r->availableOptions(classRoute(['departure_date' => '2027-03-10'])))->toBeNull();
});

test('prix unique sur la réservée : Non réservée est grisée', function () {
    classProduct();
    classFare(24, ['pricing_mode' => 'flat', 'price_flat' => 1500, 'train_name' => 'Aoniyoshi']);
    $r = new TransportFareResolver();

    expect($r->availableOptions(classRoute()))->toBe([24])
        ->and($r->fallbackOption(classRoute(['option_id' => 23])))->toBe(24);
});

test('sans la réservée, la bascule prend la première classe dans l\'ordre 24, 23, 25, 26', function () {
    classProduct();
    classFare(25);
    classFare(23);
    $r = new TransportFareResolver();

    expect($r->fallbackOption(classRoute(['option_id' => 26])))->toBe(23)
        ->and($r->fallbackOption(classRoute(['option_id' => 24])))->toBe(23);
});

test('train nommé : seules les classes de ce train (plus « tous trains ») sont disponibles', function () {
    classProduct();
    classFare(24, ['train_name' => 'Nozomi']);
    classFare(25, ['train_name' => 'Nozomi']);
    classFare(23, ['train_name' => 'Hikari']);
    classFare(26, ['train_name' => null]);
    $r = new TransportFareResolver();

    expect($r->availableOptions(classRoute(['departure_time' => 'Nozomi 243'])))->toEqualCanonicalizing([24, 25, 26])
        ->and($r->availableOptions(classRoute(['train_number' => 'Hikari 502'])))->toEqualCanonicalizing([23, 26])
        ->and($r->availableOptions(classRoute(['departure_time' => '10:00'])))->toEqualCanonicalizing([23, 24, 25, 26])
        ->and($r->availableOptions(classRoute(['train_number' => 'Sakura 1'])))->toEqualCanonicalizing([23, 24, 25, 26]);
});
