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
    $this->page = fn () => Livewire::test(EditCommercialFolder::class, ['record' => $this->folder->getRouteKey()]);
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

it('affiche le tableau des hôtels avec le trou à sa place et ouvre le panneau prérempli sur la plage', function () {
    $this->folder->stays()->create(['hotel_name' => 'A', 'check_in' => '2026-12-17', 'check_out' => '2026-12-25', 'status' => 'confirmed', 'booked_by' => 'takada']);
    $this->folder->stays()->create(['hotel_name' => 'B', 'check_in' => '2026-12-29', 'check_out' => '2027-01-03', 'status' => 'confirmed', 'booked_by' => 'takada']);

    ($this->page)()
        ->assertSee('Du 25/12 au 29/12, 4 nuits sans hébergement')
        ->assertSee('Ajouter un hôtel sur ces dates')
        ->mountAction('editStay', ['id' => null, 'check_in' => '2026-12-25', 'nights' => 4])
        ->assertActionDataSet(fn (array $data) => $data['check_in'] === '2026-12-25' && $data['check_out'] === '2026-12-29' && (int) $data['nights'] === 4);
});

it('enregistre un hôtel depuis son panneau, sous le verrou du dossier', function () {
    $lock = (int) $this->folder->fresh()->lock_version;

    ($this->page)()->callAction('editStay', data: [
        '_lock' => $lock, 'hotel_name' => 'Hôtel C', 'status' => 'to_book', 'booked_by' => 'takada', 'check_in' => '2026-12-25', 'check_out' => '2026-12-29',
    ])->assertHasNoActionErrors();

    expect($this->folder->stays()->where('hotel_name', 'Hôtel C')->exists())->toBeTrue()
        ->and((int) $this->folder->fresh()->lock_version)->toBe($lock + 1);
});

it('propose comme arrivée le départ du séjour précédent', function () {
    $this->folder->stays()->create(['hotel_name' => 'A', 'check_in' => '2026-12-17', 'check_out' => '2026-12-20', 'status' => 'confirmed', 'booked_by' => 'takada']);

    ($this->page)()->mountAction('editStay')->assertActionDataSet(fn (array $data) => $data['check_in'] === '2026-12-20');
});

it('duplique un séjour à la suite et ouvre la copie, même si une autre ligne du dossier est incomplète', function () {
    $stay = $this->folder->stays()->create(['hotel_name' => 'A', 'check_in' => '2026-12-17', 'check_out' => '2026-12-20', 'status' => 'confirmed', 'booked_by' => 'takada', 'room_lines' => [['room_type_id' => null, 'label' => 'Twin', 'quantity' => 2]]]);
    // Chaque panneau enregistre son élément : une autre donnée incomplète ne bloque rien.
    $this->folder->folderPassengers()->create(['last_name' => 'X', 'first_name' => 'Y']);

    $component = ($this->page)()->call('duplicateStay', $stay->id);

    $copy = FolderStay::where('folder_id', $this->folder->id)->where('id', '!=', $stay->id)->firstOrFail();
    expect($copy->check_in->format('Y-m-d'))->toBe('2026-12-20')->and($copy->check_out->format('Y-m-d'))->toBe('2026-12-23')
        ->and($copy->status)->toBe('to_book')->and($copy->rooms)->toBe(2);
    $component->assertActionMounted('editStay');
});

it('reprend les chambres d’un autre séjour', function () {
    $this->folder->stays()->create(['hotel_name' => 'A', 'check_in' => '2026-12-17', 'check_out' => '2026-12-20', 'status' => 'confirmed', 'booked_by' => 'takada', 'room_lines' => [['room_type_id' => null, 'label' => 'Twin', 'quantity' => 2]]]);
    $b = $this->folder->stays()->create(['hotel_name' => 'B', 'check_in' => '2026-12-20', 'check_out' => '2026-12-22', 'status' => 'confirmed', 'booked_by' => 'takada']);
    $a = $this->folder->stays()->where('hotel_name', 'A')->first();

    $component = ($this->page)()
        ->mountAction('editStay', ['id' => $b->id])
        ->setActionData(['copy_rooms_from' => 'record-' . $a->id]);

    $lines = array_values($component->instance()->mountedActions[0]['data']['room_lines'] ?? []);
    expect($lines)->toHaveCount(1)->and($lines[0]['label'])->toBe('Twin')->and((int) $lines[0]['quantity'])->toBe(2);
});

it('retire les catégories de l’ancien hôtel quand on change d’hôtel et garde les libellés saisis', function () {
    $a = Hotel::create(['name' => 'Hôtel A', 'city' => 'Tokyo']);
    $b = Hotel::create(['name' => 'Hôtel B', 'city' => 'Kyoto']);
    $double = HotelRoomType::create(['hotel_id' => $a->id, 'name' => 'Double']);
    $stay = $this->folder->stays()->create([
        'hotel_name' => 'Hôtel A', 'hotel_id' => $a->id, 'check_in' => '2026-12-21', 'check_out' => '2026-12-24', 'status' => 'confirmed', 'booked_by' => 'takada',
        'room_lines' => [['room_type_id' => $double->id, 'label' => 'Double', 'quantity' => 1], ['room_type_id' => null, 'label' => 'Twin saisie', 'quantity' => 1]],
    ]);

    $component = ($this->page)()
        ->mountAction('editStay', ['id' => $stay->id])
        ->setActionData(['hotel_id' => $b->id]);

    $lines = array_values($component->instance()->mountedActions[0]['data']['room_lines']);
    expect($lines)->toHaveCount(1)->and($lines[0]['label'])->toBe('Twin saisie');
    $component->assertNotified();
});
