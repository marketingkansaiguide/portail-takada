<?php

use App\Filament\Agency\Pages\ViewProduct;
use App\Models\FolderItem;
use App\Models\TransportFare;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->agency = World::agency();
    $this->user = World::agencyUser($this->agency);
    $this->actingAs($this->user, 'web');
    Filament::setCurrentPanel(Filament::getPanel('agency'));
    // Produit « Ticket de train / bus » (#4), classe 24 = place réservée standard.
    TransportFare::create(['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station', 'train_name' => 'Nozomi',
        'product_option_id' => 24, 'price_low' => 13970, 'price_normal' => 14170, 'price_high' => 14370, 'price_peak' => 14570]);
});

function ticketRoute(array $o = []): array
{
    return $o + ['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station', 'departure_date' => '2027-06-10',
        'departure_time' => '', 'train_number' => '', 'specific_requests' => '', 'option_id' => 24, 'pax_count' => 3,
        'children_count' => 1, 'young_children_count' => 0, 'infants_count' => 0];
}

it('un seul trajet : le total n’apparaît qu’une fois, avec le détail adultes / enfants dans le récapitulatif', function () {
    $c = Livewire::test(ViewProduct::class, ['record' => 4])
        ->set('customValues.transport_routes', [ticketRoute()]);

    $html = $c->html();
    expect(substr_count($html, '35 420 ¥'))->toBe(1)
        ->and(substr_count($html, 'Tarif indicatif, sous réserve de confirmation'))->toBe(1)
        ->and(substr_count($html, '2 ad. × 14 170 ¥ + 1 enf. × 7 080 ¥'))->toBe(1);
    $c->assertSee('Saison normale')
        ->assertDontSee('Prix des billets')
        ->assertDontSee('à confirmer auprès des compagnies');
});

it('deux trajets : prix par trajet et total, mention de tarif indicatif une seule fois', function () {
    $c = Livewire::test(ViewProduct::class, ['record' => 4])
        ->set('customValues.transport_routes', [ticketRoute(), ticketRoute(['arrival_station' => 'Nara Station'])]);

    $c->assertSee('35 420 ¥ (2 ad. × 14 170 ¥ + 1 enf. × 7 080 ¥)')
        ->assertSee('Saison normale')
        ->assertSee('Sur devis, au prix coûtant')
        ->assertSee('1 trajet sans tarif connu');
    // Prix du trajet 1 sous le trajet + total des billets dans le récapitulatif (ici identiques : un seul trajet chiffré).
    expect(substr_count($c->html(), '35 420 ¥'))->toBe(2)
        ->and(substr_count($c->html(), 'Tarif indicatif, sous réserve de confirmation'))->toBe(1);
});

it('deux trajets chiffrés : chaque prix de trajet puis le total', function () {
    TransportFare::create(['departure_station' => 'Kyoto Station', 'arrival_station' => 'Osaka Station', 'train_name' => 'Nozomi',
        'product_option_id' => 24, 'price_low' => 1000, 'price_normal' => 1000, 'price_high' => 1000, 'price_peak' => 1000]);

    $c = Livewire::test(ViewProduct::class, ['record' => 4])
        ->set('customValues.transport_routes', [ticketRoute(), ticketRoute(['departure_station' => 'Kyoto Station', 'arrival_station' => 'Osaka Station', 'pax_count' => 1, 'children_count' => 0])]);

    $c->assertSee('35 420 ¥ (2 ad. × 14 170 ¥ + 1 enf. × 7 080 ¥)')
        ->assertSee('1 000 ¥ (1 ad. × 1 000 ¥)')
        ->assertSee('36 420 ¥');
});

it('n’affiche pas la mention de tarif indicatif quand aucun trajet n’a de prix', function () {
    Livewire::test(ViewProduct::class, ['record' => 4])
        ->set('customValues.transport_routes', [ticketRoute(['arrival_station' => 'Nara Station'])])
        ->assertSee('Sur devis, au prix coûtant')
        ->assertDontSee('Tarif indicatif, sous réserve de confirmation')
        ->assertDontSee('à confirmer auprès des compagnies');
});

it('enregistre le prix indicatif dans le trajet commandé', function () {
    $folder = World::folder($this->agency);

    Livewire::test(ViewProduct::class, ['record' => 4])
        ->set('selectedFolderId', $folder->id)
        ->set('customValues.transport_routes', [ticketRoute(), ticketRoute(['arrival_station' => 'Nara Station'])])
        ->call('addToFolder');

    $routes = FolderItem::where('folder_id', $folder->id)->firstOrFail()->custom_values['transport_routes'];
    expect($routes[0]['fare_estimate']['total'])->toBe(35420)
        ->and($routes[0]['fare_estimate']['children'])->toBe(1)
        ->and($routes[0]['children_count'])->toBe(1)
        ->and($routes[1])->not->toHaveKey('fare_estimate');
});

it('n’embarque pas la liste complète des gares dans la page', function () {
    foreach (range(1, 300) as $i) {
        \App\Models\TrainStation::create(['name_en' => "Testville{$i} Station", 'importance_score' => $i % 90]);
    }

    $c = Livewire::test(ViewProduct::class, ['record' => 4]);
    expect(substr_count($c->html(), 'Testville250 Station'))->toBe(0)
        ->and(strlen($c->html()))->toBeLessThan(400_000);
});

it('recherche de gares : exact d’abord, puis préfixe, puis le reste, à rang égal la plus importante', function () {
    $mk = fn ($en, $ja, $city, $score) => \App\Models\TrainStation::create(['name_en' => $en, 'name_ja' => $ja, 'city' => $city, 'importance_score' => $score]);
    $mk('Shinagawa Station', '品川', 'Tokyo', 95);       // ville = Tokyo, très importante
    $mk('Tokyo Disneyland Station', '舞浜', 'Urayasu', 40); // préfixe
    $mk('Shin-Tokyo Station', '新東京', 'X', 90);           // contient
    $mk('Tokyo Station', '東京', 'Chiyoda City', 80);       // exact (« tokyo station »)
    $mk('Tokyo', '東京都', 'Y', 1);                         // exact (« tokyo »)

    $names = array_column(\App\Support\StationSearch::search('Tokyo', 15), 'name');
    expect($names[0])->toBe('Tokyo Station')
        ->and($names[1])->toBe('Tokyo')
        ->and($names[2])->toBe('Tokyo Disneyland Station')
        ->and($names[3])->toBe('Shin-Tokyo Station')
        ->and($names[4])->toBe('Shinagawa Station');

    expect(array_column(\App\Support\StationSearch::search('tokyo station', 15), 'name')[0])->toBe('Tokyo Station')
        ->and(\App\Support\StationSearch::search('100%_zzz'))->toBe([])
        ->and(\App\Support\StationSearch::search(null, 3))->toHaveCount(3);
});

it('le point d’accès de recherche de gares exige une connexion et renvoie du JSON', function () {
    \App\Models\TrainStation::create(['name_en' => 'Zzyzx Station', 'name_ja' => '終点駅', 'importance_score' => 1]);

    $this->getJson(route('transport.stations', ['q' => 'zzyzx']))
        ->assertOk()->assertJsonPath('0.name', 'Zzyzx Station');

    auth('web')->logout();
    expect($this->getJson(route('transport.stations', ['q' => 'zzyzx']))->getStatusCode())->not->toBe(200);
});
