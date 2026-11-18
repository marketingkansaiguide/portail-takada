<?php

use App\Models\TransportFare;
use App\Models\TransportFareSeason;
use App\Support\TransportFareCalendar;
use App\Support\TransportFareResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function calFare(string $train, int $optionId, array $o = []): TransportFare
{
    return TransportFare::create($o + ['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station',
        'train_name' => $train, 'product_option_id' => $optionId,
        'price_low' => 100, 'price_normal' => 200, 'price_high' => 300, 'price_peak' => 400]);
}

test('le classifieur attribue le calendrier selon le nom du train', function () {
    $s = TransportFare::MODE_SEASONAL;
    expect(TransportFareCalendar::forTrain('Nozomi', $s))->toBe('A')
        ->and(TransportFareCalendar::forTrain('HAYABUSA', $s))->toBe('B')
        ->and(TransportFareCalendar::forTrain('Toki', $s))->toBe('B')
        ->and(TransportFareCalendar::forTrain('Tokiwa', $s))->toBeNull()
        ->and(TransportFareCalendar::forTrain('Fuji Excursion', $s))->toBeNull()
        ->and(TransportFareCalendar::forTrain('Hokuto', $s))->toBeNull()
        ->and(TransportFareCalendar::forTrain('Sonic', $s))->toBe('K')
        ->and(TransportFareCalendar::forTrain('Relay Kamome', $s))->toBe('K')
        ->and(TransportFareCalendar::forTrain('Sakura', $s))->toBe('A')
        ->and(TransportFareCalendar::forTrain('Sunrise Izumo', $s))->toBe('A')
        ->and(TransportFareCalendar::forTrain('Train inconnu', $s))->toBe('A')
        ->and(TransportFareCalendar::forTrain(null, $s))->toBe('A')
        ->and(TransportFareCalendar::forTrain('Asoboy', $s))->toBe('K')
        ->and(TransportFareCalendar::forTrain('Ise-Shima Liner', $s))->toBeNull()
        ->and(TransportFareCalendar::forTrain('Vista Car', $s))->toBeNull()
        ->and(TransportFareCalendar::forTrain('Narita Express', $s))->toBe('B')
        ->and(TransportFareCalendar::forTrain('Super Tsugaru', $s))->toBe('B')
        ->and(TransportFareCalendar::forTrain('Nampu', $s))->toBe('A')
        ->and(TransportFareCalendar::forTrain('Konotori', $s))->toBe('A')
        ->and(TransportFareCalendar::forTrain('Hayabusa + Hokuto', $s))->toBe('B')
        ->and(TransportFareCalendar::forTrain('Nozomi', TransportFare::MODE_FLAT))->toBeNull();
});

test('le calendrier est déduit à la création sauf s\'il est fixé explicitement', function () {
    $o = fareOptions()['reserved'];
    expect(calFare('Hayabusa', $o->id)->season_calendar)->toBe('B')
        ->and(calFare('Hayabusa', $o->id, ['season_calendar' => null])->season_calendar)->toBeNull()
        ->and(calFare('Hayabusa', $o->id, ['season_calendar' => 'A'])->season_calendar)->toBe('A')
        ->and(calFare('Nozomi', $o->id, ['pricing_mode' => 'flat', 'price_flat' => 500])->season_calendar)->toBeNull();
});

test('une période du calendrier B change un Hayabusa mais pas un Nozomi', function () {
    $o = fareOptions()['reserved'];
    calFare('Nozomi', $o->id);
    calFare('Hayabusa', $o->id);
    TransportFareSeason::create(['company' => 'B', 'season' => 'peak', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-31']);

    $r = new TransportFareResolver();
    $nozomi = $r->forRoute(fareRoute(['option_id' => $o->id, 'departure_time' => 'Nozomi 1']));
    $hayabusa = $r->forRoute(fareRoute(['option_id' => $o->id, 'departure_time' => 'Hayabusa 1']));
    expect($nozomi['unit_price'])->toBe(200)->and($nozomi['season'])->toBe('normal')
        ->and($hayabusa['unit_price'])->toBe(400)->and($hayabusa['season'])->toBe('peak');
});

test('un tarif sans calendrier reste au prix normal', function () {
    $o = fareOptions()['reserved'];
    calFare('Azusa', $o->id);
    TransportFareSeason::create(['company' => 'A', 'season' => 'peak', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-31']);
    TransportFareSeason::create(['company' => 'B', 'season' => 'peak', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-31']);

    $res = (new TransportFareResolver())->forRoute(fareRoute(['option_id' => $o->id]));
    expect($res['unit_price'])->toBe(200)->and($res['season'])->toBe('normal');
});

test('le calendrier K suit A sans saison basse', function () {
    $o = fareOptions()['reserved'];
    calFare('Sonic', $o->id);
    TransportFareSeason::create(['company' => 'A', 'season' => 'low', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-31']);

    $r = new TransportFareResolver();
    $low = $r->forRoute(fareRoute(['option_id' => $o->id]));
    expect($low['unit_price'])->toBe(200)->and($low['season'])->toBe('normal');

    TransportFareSeason::create(['company' => 'A', 'season' => 'high', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-31']);
    $high = (new TransportFareResolver())->forRoute(fareRoute(['option_id' => $o->id]));
    expect($high['unit_price'])->toBe(300)->and($high['season'])->toBe('high');

    expect(TransportFareSeason::seasonFor('2027-03-10', 'A'))->toBe('high');
});
