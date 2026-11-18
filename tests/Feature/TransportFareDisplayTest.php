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

it('affiche le prix indicatif du trajet connu et « sur devis » pour un trajet absent de la grille', function () {
    Livewire::test(ViewProduct::class, ['record' => 4])
        ->set('customValues.transport_routes', [ticketRoute(), ticketRoute(['arrival_station' => 'Nara Station'])])
        ->assertSee('35 420 ¥ (2 ad. × 14 170 ¥ + 1 enf. × 7 080 ¥)')
        ->assertSee('Prix indicatif, à confirmer auprès des compagnies')
        ->assertSee('Tarif indicatif, sous réserve de confirmation auprès de la ou des compagnies de train/bus')
        ->assertSee('saison normale')
        ->assertSee('Sur devis, au prix coûtant')
        ->assertSee('1 trajet sans tarif connu');
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
