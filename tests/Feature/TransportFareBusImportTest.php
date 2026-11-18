<?php

use App\Models\BusStation;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\TrainStation;
use App\Models\TransportFare;
use App\Support\TransportFareImporter;
use App\Support\TransportFareResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

function busSetup(): void
{
    $category = Category::forceCreate(['name' => 'Transport']);
    $product = Product::forceCreate(['name' => 'Ticket de train / bus', 'product_type' => 'transport', 'category_id' => $category->id]);
    foreach ([23 => 'Place non réservée', 24 => 'Place réservée', 25 => 'Green', 26 => 'Gran Class'] as $id => $name) {
        ProductOption::forceCreate(['id' => $id, 'product_id' => $product->id, 'name' => $name]);
    }
    foreach (['Takayama (bus)', 'Shirakawago (bus)', 'Kawaguchiko (bus)', 'Shinjuku (bus)', 'Kyoto (bus)'] as $n) {
        BusStation::forceCreate(['name_en' => $n]);
    }
    foreach (['Mishima Station', 'Kyoto Station', 'Tokyo Station', 'Shinjuku Station'] as $n) {
        TrainStation::forceCreate(['name_en' => $n]);
    }
}

/** @param array<int, array<int, mixed>> $rows lignes de données (à partir de la ligne 2 de l'onglet « Bus ») */
function busFile(array $rows): string
{
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet()->setTitle('Bus');
    $sheet->fromArray(['Publier', 'Arrêt A', 'Arrêt B', 'Deux sens', 'Compagnie', 'Prix adulte', 'Prix enfant', 'Du', 'Au', 'Remarques'], null, 'A1');
    $sheet->fromArray($rows, null, 'A2');
    $path = tempnam(sys_get_temp_dir(), 'bus') . '.xlsx';
    (new Xlsx($book))->save($path);

    return $path;
}

function trainFile(): string
{
    $book = new Spreadsheet();
    $book->getActiveSheet()->setTitle('Tarifs');
    $path = tempnam(sys_get_temp_dir(), 'trn') . '.xlsx';
    (new Xlsx($book))->save($path);

    return $path;
}

function busRoute(string $from, string $to, array $o = []): array
{
    return $o + ['departure_station' => $from, 'arrival_station' => $to, 'departure_date' => '2027-03-10', 'pax_count' => 2, 'option_id' => 24];
}

const TAKAYAMA_ROW = ['Oui', 'Takayama (bus)', 'Shirakawago (bus)', 'Oui', 'Nohi Bus', 4900, '', '', '', ''];

test('un import bus ne touche à aucune ligne train, et un import train ne touche pas aux lignes bus', function () {
    busSetup();
    $train = TransportFare::create(['departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station', 'product_option_id' => 24, 'price_normal' => 14170, 'source' => TransportFareImporter::SOURCE]);
    $manual = TransportFare::create(['departure_station' => 'Tokyo Station', 'arrival_station' => 'Mishima Station', 'product_option_id' => 24, 'price_normal' => 5000, 'source' => 'manuel']);

    $importer = new TransportFareImporter();
    $r = $importer->import(busFile([TAKAYAMA_ROW]));
    expect($r['kind'])->toBe('bus')->and($r['fares'])->toBe(1);
    expect(TransportFare::where('source', 'import-excel')->pluck('id')->all())->toBe([$train->id])
        ->and($manual->fresh())->not->toBeNull();

    // Réimport bus : remplace uniquement les lignes bus.
    $row = TAKAYAMA_ROW;
    $row[5] = 5000;
    $importer->import(busFile([$row]), false, 'bus');
    expect(TransportFare::where('source', 'import-excel-bus')->count())->toBe(1)
        ->and(TransportFare::where('source', 'import-excel-bus')->first()->price_flat)->toBe(5000)
        ->and($train->fresh())->not->toBeNull();

    // Import train (fichier sans ligne) : remplace les lignes train, garde les bus et les saisies manuelles.
    $r = $importer->import(trainFile());
    expect($r['kind'])->toBe('train')
        ->and(TransportFare::where('source', 'import-excel')->count())->toBe(0)
        ->and(TransportFare::where('source', 'import-excel-bus')->count())->toBe(1)
        ->and($manual->fresh())->not->toBeNull();
});

test('prix trouvé pour un trajet arrêt de bus vers arrêt de bus, dans les deux sens, avec le name_en choisi par l’agence', function () {
    busSetup();
    $row = TAKAYAMA_ROW;
    $row[9] = 'Réservation conseillée';
    (new TransportFareImporter())->import(busFile([$row]));

    $fare = TransportFare::where('source', 'import-excel-bus')->first();
    expect($fare->pricing_mode)->toBe('flat')->and($fare->product_option_id)->toBe(24)->and($fare->train_name)->toBeNull()
        ->and($fare->season_calendar)->toBeNull()->and($fare->notes)->toBe('Compagnie : Nohi Bus. Réservation conseillée')
        ->and($fare->departure_key)->toBe('takayamabus')->and($fare->arrival_key)->toBe('shirakawagobus');

    $resolver = new TransportFareResolver();
    $go = $resolver->forRoute(busRoute('Takayama (bus)', 'Shirakawago (bus)'));
    $back = $resolver->forRoute(busRoute('Shirakawago (bus)', 'Takayama (bus)'));
    expect($go['unit_price'])->toBe(4900)->and($go['total'])->toBe(9800)->and($go['child_price'])->toBe(2450)
        ->and($back['unit_price'])->toBe(4900);
});

test('un arrêt de bus n’est pas confondu avec la gare de train du même nom', function () {
    busSetup();
    TransportFare::create(['departure_station' => 'Kyoto Station', 'arrival_station' => 'Tokyo Station', 'product_option_id' => 24, 'price_normal' => 14170, 'source' => 'import-excel']);

    $resolver = new TransportFareResolver();
    expect($resolver->forRoute(busRoute('Kyoto Station', 'Tokyo Station'))['unit_price'])->toBe(14170)
        ->and($resolver->forRoute(busRoute('Kyoto (bus)', 'Tokyo Station')))->toBeNull();
});

test('trajet mixte gare de train et arrêt de bus', function () {
    busSetup();
    (new TransportFareImporter())->import(busFile([['Oui', 'Mishima Station', 'Kawaguchiko (bus)', 'Oui', 'Fujikyu', 2000, 1000, '', '', '']]));

    $resolver = new TransportFareResolver();
    expect($resolver->forRoute(busRoute('Mishima Station', 'Kawaguchiko (bus)'))['unit_price'])->toBe(2000)
        ->and($resolver->forRoute(busRoute('Kawaguchiko (bus)', 'Mishima Station'))['child_price'])->toBe(1000)
        ->and($resolver->forRoute(busRoute('Mishima', 'kawaguchiko (bus)'))['unit_price'])->toBe(2000);
});

test('seule la place réservée est disponible sur un trajet bus de la grille, les autres classes sont grisées', function () {
    busSetup();
    (new TransportFareImporter())->import(busFile([TAKAYAMA_ROW]));
    $resolver = new TransportFareResolver();

    expect($resolver->availableOptions(busRoute('Takayama (bus)', 'Shirakawago (bus)')))->toBe([24])
        ->and($resolver->fallbackOption(busRoute('Shirakawago (bus)', 'Takayama (bus)', ['option_id' => 25])))->toBe(24)
        ->and($resolver->availableOptions(busRoute('Takayama (bus)', 'Kyoto (bus)')))->toBeNull();
});

test('lignes Non, arrêts vides, ⚠ ou inconnus : ignorées et signalées avec le nom exact', function () {
    busSetup();
    $result = (new TransportFareImporter())->import(busFile([
        TAKAYAMA_ROW,                                                                                  // ligne 2 : valide
        ['Non', 'Shinjuku (bus)', 'Kawaguchiko (bus)', 'Oui', 'Fujikyu', 2000, '', '', '', ''],        // ligne 3 : Non
        ['Oui', '', 'Kawaguchiko (bus)', 'Oui', 'Fujikyu', 2000, '', '', '', ''],                      // ligne 4 : arrêt vide
        ['Oui', '⚠ Shinjuku', 'Kawaguchiko (bus)', 'Oui', 'Fujikyu', 2000, '', '', '', ''],           // ligne 5 : ⚠
        ['Oui', 'Takayama', 'Shirakawago (bus)', 'Oui', 'Nohi Bus', 4900, '', '', '', ''],            // ligne 6 : inconnu
        ['Oui', 'Shinjuku (bus)', 'Kawaguchiko (bus)', 'Oui', 'Fujikyu', '', '', '', '', ''],         // ligne 7 : prix manquant
    ]));

    expect($result['fares'])->toBe(1)->and(array_keys($result['skipped']))->toBe([3, 4, 5, 6, 7])
        ->and($result['skipped'][6])->toContain('« Takayama »')->and($result['skipped'][6])->toContain('« Takayama (bus) »');
    expect(TransportFare::where('source', 'import-excel-bus')->count())->toBe(1);
});

test('la vérification à blanc n’enregistre rien', function () {
    busSetup();
    $r = (new TransportFareImporter())->import(busFile([TAKAYAMA_ROW]), true);
    expect($r['fares'])->toBe(1)->and(TransportFare::count())->toBe(0);
});
