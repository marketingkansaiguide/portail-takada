<?php

use App\Filament\Commercial\Resources\CommercialFolderResource\Pages\EditCommercialFolder;
use App\Models\FolderStay;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->fit = World::staff('commercial_fit');
    $this->actingAs($this->fit, 'web');
    Filament::setCurrentPanel(Filament::getPanel('commercial'));
    $this->folder = World::internalFolder(null, ['owner_user_id' => $this->fit->id, 'ops_status' => 'en_preparation', 'kind' => 'full_trip', 'start_date' => '2026-12-17', 'end_date' => '2027-01-03']);
});

it('plusieurs catégories de chambre alimentent les colonnes historiques', function () {
    $hotel = Hotel::create(['name' => 'Hôtel Exemple', 'city' => 'Kanazawa']);
    $double = HotelRoomType::create(['hotel_id' => $hotel->id, 'name' => 'Double']);
    $stay = $this->folder->stays()->create([
        'hotel_name' => 'Hôtel Exemple', 'hotel_id' => $hotel->id, 'check_in' => '2026-12-21', 'check_out' => '2026-12-24', 'status' => 'confirmed',
        'room_lines' => [['room_type_id' => $double->id, 'label' => 'Double', 'quantity' => 1], ['room_type_id' => null, 'label' => 'Twin', 'quantity' => 1]],
    ]);

    $stay->refresh();
    expect($stay->rooms)->toBe(2)->and($stay->room_type_id)->toBe($double->id)->and($stay->room_label)->toBe('Double')
        ->and($stay->roomsSummary())->toBe('1 × Double + 1 × Twin');
});

it('affiche le tableau des hôtels avec le trou à sa place et ajoute un séjour sur la plage', function () {
    $this->folder->stays()->create(['hotel_name' => 'A', 'check_in' => '2026-12-17', 'check_out' => '2026-12-25', 'status' => 'confirmed', 'booked_by' => 'takada']);
    $this->folder->stays()->create(['hotel_name' => 'B', 'check_in' => '2026-12-29', 'check_out' => '2027-01-03', 'status' => 'confirmed', 'booked_by' => 'takada']);

    Livewire::test(EditCommercialFolder::class, ['record' => $this->folder->getRouteKey()])
        ->assertSee('25/12 → 28/12')
        ->assertSee('Ajouter un hôtel sur ces dates')
        ->call('addStayOnGap', '2026-12-25', 4);

    expect($this->folder->stays()->count())->toBe(3)
        ->and($this->folder->stays()->where('hotel_name', 'Hôtel à préciser')->first()->check_out->format('Y-m-d'))->toBe('2026-12-29');
});

it('duplique un séjour à la suite avec les mêmes chambres', function () {
    $stay = $this->folder->stays()->create(['hotel_name' => 'A', 'check_in' => '2026-12-17', 'check_out' => '2026-12-20', 'status' => 'confirmed', 'booked_by' => 'takada', 'room_lines' => [['room_type_id' => null, 'label' => 'Twin', 'quantity' => 2]]]);

    Livewire::test(EditCommercialFolder::class, ['record' => $this->folder->getRouteKey()])->call('duplicateStay', $stay->id);

    $copy = FolderStay::where('folder_id', $this->folder->id)->where('id', '!=', $stay->id)->firstOrFail();
    expect($copy->check_in->format('Y-m-d'))->toBe('2026-12-20')->and($copy->check_out->format('Y-m-d'))->toBe('2026-12-23')
        ->and($copy->rooms)->toBe(2);
});
