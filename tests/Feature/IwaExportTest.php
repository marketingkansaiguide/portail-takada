<?php

use App\Exports\IwaTicketsExport;
use App\Filament\Pages\IwaOrders;
use App\Models\Folder;
use App\Models\FolderItem;
use App\Support\TransportFareResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

function iwaSchema(): void
{
    Schema::create('folders', function (Blueprint $t) {
        $t->id();
        $t->string('folder_name')->nullable();
        $t->string('lead_traveler_name')->nullable();
        $t->date('start_date')->nullable();
        $t->date('end_date')->nullable();
        $t->string('status')->default('confirmed');
        $t->unsignedBigInteger('agency_id')->nullable();
        $t->integer('pax_adults')->nullable();
        $t->integer('pax_children')->nullable();
        $t->timestamps();
    });
    Schema::create('item_statuses', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('color')->nullable();
        $t->timestamps();
    });
    Schema::create('folder_items', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('folder_id');
        $t->unsignedBigInteger('product_id')->nullable();
        $t->unsignedBigInteger('supplier_id')->nullable();
        $t->unsignedBigInteger('item_status_id')->nullable();
        $t->date('service_date')->nullable();
        $t->text('custom_values')->nullable();
        $t->string('import_ref')->nullable();
        $t->timestamp('label_exported_at')->nullable();
        $t->timestamp('iwa_exported_at')->nullable();
        $t->timestamps();
    });
    foreach (['products', 'agencies', 'folder_passengers'] as $table) {
        Schema::create($table, function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('folder_id')->nullable();
            $t->timestamps();
        });
    }
    Schema::create('product_suppliers', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('product_id');
        $t->unsignedBigInteger('supplier_id');
        $t->timestamps();
    });
    Schema::create('train_stations', function (Blueprint $t) {
        $t->id();
        $t->string('name_en');
        $t->string('name_ja')->nullable();
        $t->timestamps();
    });

    \Illuminate\Support\Facades\DB::table('item_statuses')->insert(['id' => 1, 'name' => "En attente d'ouverture"]);
}

function iwaFolder(string $name, string $start, string $end, string $traveler = 'Dupont'): Folder
{
    return Folder::withoutEvents(fn () => Folder::unguarded(fn () => Folder::create([
        'folder_name' => $name, 'lead_traveler_name' => $traveler,
        'start_date' => $start, 'end_date' => $end, 'status' => 'confirmed',
    ])));
}

function iwaItem(Folder $folder, array $routes, array $extra = []): FolderItem
{
    return FolderItem::withoutEvents(fn () => FolderItem::unguarded(fn () => FolderItem::create(array_merge([
        'folder_id' => $folder->id, 'item_status_id' => 1,
        'custom_values' => $routes === null ? null : ['transport_routes' => $routes],
    ], $extra))));
}

function iwaRoute(array $over = []): array
{
    return array_merge([
        'departure_station' => 'Tokyo Station', 'arrival_station' => 'Kyoto Station',
        'departure_date' => '2026-11-02', 'departure_time' => '09:30', 'arrival_time' => '11:49',
        'train_number' => 'Nozomi 13', 'option_id' => 24, 'pax_count' => 2,
    ], $over);
}

/** Ligne d'aperçu telle que la page l'alimente. */
function iwaRow(array $over = []): array
{
    return array_merge([
        'item_id' => 1, 'route_index' => 0, 'folder_id' => 1, 'folder_start' => '2026-11-01',
        'pax_name' => 'Dupont', 'date' => '2026-11-02', 'train_name' => 'Nozomi 13',
        'departure' => 'Tokyo Station', 'arrival' => 'Kyoto Station',
        'dep_time' => '09:30', 'arr_time' => '11:49', 'class' => '指定席',
        'pax_count' => 2, 'children_count' => 0, 'young_children_count' => 0, 'infants_count' => 0,
        'specific_requests' => '', 'fare_ticket_mode' => 'same',
    ], $over);
}

function iwaLoad(array $rows): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'iwa') . '.xlsx';
    file_put_contents($path, Excel::raw(new IwaTicketsExport($rows), ExcelWriter::XLSX));
    $book = IOFactory::load($path);
    @unlink($path);

    return $book;
}

beforeEach(fn () => iwaSchema());

test('le classeur a un onglet par mois, nommé et ordonné chronologiquement', function () {
    $book = iwaLoad([
        iwaRow(['folder_start' => '2026-11-03', 'date' => '2026-11-05', 'pax_name' => 'Martin']),
        iwaRow(['folder_start' => '2026-10-20', 'date' => '2026-10-21']),
    ]);

    expect($book->getSheetNames())->toBe(['Dde IWA Oct', 'Dde IWA Nov']);
});

test("l'année n'apparaît que si le même mois existe sur deux années", function () {
    $book = iwaLoad([
        iwaRow(['folder_start' => '2026-10-20']),
        iwaRow(['folder_start' => '2027-10-02', 'date' => '2027-10-03']),
        iwaRow(['folder_start' => '2026-12-02', 'date' => '2026-12-03']),
    ]);

    expect($book->getSheetNames())->toBe(['Dde IWA Oct 2026', 'Dde IWA Déc', 'Dde IWA Oct 2027']);
});

test("un dossier du 28/10 au 05/11 sort entièrement dans l'onglet Oct", function () {
    $book = iwaLoad([
        iwaRow(['folder_id' => 1, 'folder_start' => '2026-10-28', 'date' => '2026-10-29']),
        iwaRow(['folder_id' => 1, 'folder_start' => '2026-10-28', 'date' => '2026-11-04', 'dep_time' => '8:05']),
        iwaRow(['folder_id' => 2, 'folder_start' => '2026-11-03', 'date' => '2026-11-04', 'pax_name' => 'Martin']),
    ]);

    expect($book->getSheetNames())->toBe(['Dde IWA Oct', 'Dde IWA Nov']);
    $oct = $book->getSheetByName('Dde IWA Oct');
    expect($oct->getCell('B5')->getValue())->toBe('Dupont様')
        ->and($oct->getCell('B6')->getValue())->toBe('Dupont様')
        ->and($oct->getCell('B7')->getValue())->toBeNull()
        ->and($oct->getCell('E6')->getValue())->toBe('08:05')
        ->and($book->getSheetByName('Dde IWA Nov')->getCell('B5')->getValue())->toBe('Martin様');
});

test("mise en page de l'onglet : en-têtes, volets figés, filtre, largeurs, date Excel", function () {
    $sheet = iwaLoad([iwaRow(['date' => '2026-11-02'])])->getSheet(0);

    expect($sheet->getCell('B4')->getValue())->toBe('お客様名')
        ->and($sheet->getCell('D4')->getValue())->toBe('列車名/区間/設備/乗車券')
        ->and($sheet->getCell('G4')->getValue())->toBe('人数')
        ->and($sheet->getCell('B2')->getValue())->toContain('TAKADA TRAVEL')
        ->and($sheet->getCell('B1')->getValue())->toBeNull()
        ->and($sheet->getCell('H4')->getValue())->toBeNull()
        ->and($sheet->getFreezePane())->toBe('A5')
        ->and($sheet->getAutoFilter()->getRange())->toBe('B4:G5')
        ->and($sheet->getColumnDimension('D')->getWidth())->toBe(42.0)
        ->and($sheet->getStyle('B4')->getFill()->getStartColor()->getRGB())->toBe('C9DAF8')
        ->and($sheet->getStyle('D5')->getAlignment()->getWrapText())->toBeTrue();

    $c = $sheet->getCell('C5');
    expect($c->getStyle()->getNumberFormat()->getFormatCode())->toBe('yyyy"年"m"月"d"日"')
        ->and(Date::excelToDateTimeObject($c->getValue())->format('Y-m-d'))->toBe('2026-11-02')
        ->and($sheet->getCell('E5')->getValue())->toBe('09:30')
        ->and($sheet->getCell('F5')->getValue())->toBe('11:49');
});

test('cellule D : modes same, custom et none', function () {
    $ja = ['Tokyo Station' => '東京駅', 'Kyoto Station' => '京都駅', 'Nagoya Station' => '名古屋駅'];

    expect(IwaTicketsExport::detailCell(iwaRow(), $ja))
        ->toBe("Nozomi 13\n東京 - 京都\n指定席\n乗車券 東京～京都");

    expect(IwaTicketsExport::detailCell(iwaRow([
        'fare_ticket_mode' => 'custom', 'fare_ticket_from' => 'Tokyo Station', 'fare_ticket_to' => 'Nagoya Station',
        'specific_requests' => 'Côté Mont Fuji',
    ]), $ja))->toBe("Nozomi 13\n東京 - 京都\n指定席－Côté Mont Fuji\n乗車券 東京～名古屋");

    expect(IwaTicketsExport::detailCell(iwaRow(['fare_ticket_mode' => 'none']), $ja))
        ->toBe("Nozomi 13\n東京 - 京都\n指定席");

    // Clé absente = « même trajet » ; gare sans name_ja (arrêt de bus) = nom anglais court.
    $row = iwaRow(['arrival' => 'Takayama Bus Terminal (Nohi)']);
    unset($row['fare_ticket_mode']);
    expect(IwaTicketsExport::detailCell($row, $ja))->toEndWith('乗車券 東京～Takayama Bus Terminal');
});

test('noms de gares raccourcis en anglais court', function () {
    expect(IwaTicketsExport::shortName('Hakata Station (Gare Shinkansen)'))->toBe('Hakata')
        ->and(IwaTicketsExport::shortName('Shin-Osaka Sta.'))->toBe('Shin-Osaka')
        ->and(IwaTicketsExport::shortName('  Kyoto   Station '))->toBe('Kyoto');
});

test('la cellule D lue dans le classeur utilise name_ja de la gare, sans le suffixe 駅', function () {
    \Illuminate\Support\Facades\DB::table('train_stations')->insert([
        ['name_en' => 'Tokyo Station', 'name_ja' => '東京駅'], ['name_en' => 'Kyoto Station', 'name_ja' => '京都駅'],
    ]);
    $sheet = iwaLoad([iwaRow()])->getSheet(0);

    expect($sheet->getCell('D5')->getValue())->toBe("Nozomi 13\n東京 - 京都\n指定席\n乗車券 東京～京都");
});

test('voyageurs : nombre seul sans enfant payant, « 大 n / 小 m » avec un enfant', function () {
    expect(IwaTicketsExport::passengersCell(iwaRow()))->toBe(2)
        ->and(IwaTicketsExport::passengersCell(iwaRow(['pax_count' => 3, 'children_count' => 1])))->toBe('大 2 / 小 1');

    $sheet = iwaLoad([iwaRow(['pax_count' => 3, 'children_count' => 1])])->getSheet(0);
    expect($sheet->getCell('G5')->getValue())->toBe('大 2 / 小 1');
});

test('marquage IWA, annulation, et une impression d\'étiquette ne masque plus la prestation', function () {
    $item = iwaItem(iwaFolder('F1', '2026-11-01', '2026-11-08'), [iwaRoute()], ['label_exported_at' => now()]);

    // Étiquettes imprimées : toujours « à envoyer » à IWA.
    $pending = fn () => IwaOrders::getIwaItemsQuery()->whereNull('iwa_exported_at')->pluck('id')->all();
    expect($pending())->toBe([$item->id]);

    IwaOrders::markSent([$item->id]);
    expect($pending())->toBe([])
        ->and($item->fresh()->iwa_exported_at)->not->toBeNull()
        ->and($item->fresh()->label_exported_at)->not->toBeNull();

    IwaOrders::cancelSent([$item->id]);
    expect($pending())->toBe([$item->id])->and($item->fresh()->iwa_exported_at)->toBeNull();
});

test('la liste IWA exclut les prestations sans trajet', function () {
    $folder = iwaFolder('F1', '2026-11-01', '2026-11-08');
    $withRoute = iwaItem($folder, [iwaRoute()]);
    $noRoutes = iwaItem($folder, []);
    $nullValues = FolderItem::withoutEvents(fn () => FolderItem::unguarded(fn () => FolderItem::create([
        'folder_id' => $folder->id, 'item_status_id' => 1,
    ])));

    $ids = IwaOrders::getIwaItemsQuery()->pluck('id')->all();
    expect($ids)->toBe([$withRoute->id])
        ->and($ids)->not->toContain($noRoutes->id, $nullValues->id);
});

test('le filtre de période porte sur le début du dossier : le dossier 28/10-05/11 est dans Oct, pas dans Nov', function () {
    $straddle = iwaItem(iwaFolder('A', '2026-10-28', '2026-11-05'), [iwaRoute(['departure_date' => '2026-11-04'])], ['service_date' => '2026-11-04']);
    $november = iwaItem(iwaFolder('B', '2026-11-10', '2026-11-15'), [iwaRoute(['departure_date' => '2026-11-11'])], ['service_date' => '2026-11-11']);

    $ids = fn (string $from, string $until) => IwaOrders::filterByFolderStart(IwaOrders::getIwaItemsQuery(), $from, $until)->pluck('id')->all();

    expect($ids('2026-10-01', '2026-10-31'))->toBe([$straddle->id])
        ->and($ids('2026-11-01', '2026-11-30'))->toBe([$november->id]);
});

test('la liste regroupe les prestations par dossier, dossiers par début puis par date', function () {
    $late = iwaFolder('Tardif', '2026-11-20', '2026-11-25');
    $early = iwaFolder('Précoce', '2026-11-02', '2026-11-09');
    $i1 = iwaItem($late, [iwaRoute()], ['service_date' => '2026-11-21']);
    $i2 = iwaItem($early, [iwaRoute()], ['service_date' => '2026-11-08']);
    $i3 = iwaItem($late, [iwaRoute()], ['service_date' => '2026-11-20']);
    $i4 = iwaItem($early, [iwaRoute()], ['service_date' => '2026-11-03']);

    $ordered = IwaOrders::orderByFolderThenDate(IwaOrders::getIwaItemsQuery())->pluck('id')->all();

    expect($ordered)->toBe([$i4->id, $i2->id, $i3->id, $i1->id]);
    expect(IwaOrders::folderGroupLabel(IwaOrders::getIwaItemsQuery()->with('folder')->find($i4->id)))
        ->toBe('Précoce — Dupont — 02/11/2026 → 09/11/2026');
});

test("l'aperçu présente les trajets par dossier puis par date et porte les voyageurs du trajet", function () {
    $b = iwaFolder('B', '2026-11-10', '2026-11-12', 'Martin');
    $a = iwaFolder('A', '2026-11-01', '2026-11-12', 'Dupont');
    $ib = iwaItem($b, [iwaRoute(['departure_date' => '2026-11-10'])]);
    $ia = iwaItem($a, [
        iwaRoute(['departure_date' => '2026-11-09', 'pax_count' => 3, 'children_count' => 1]),
        iwaRoute(['departure_date' => '2026-11-02']),
    ]);

    $rows = IwaOrders::buildPreviewRows(FolderItem::with('folder')->get());

    expect(array_column($rows, 'pax_name'))->toBe(['Dupont', 'Dupont', 'Martin'])
        ->and(array_column($rows, 'date'))->toBe(['2026-11-02', '2026-11-09', '2026-11-10'])
        ->and($rows[1]['children_count'])->toBe(1)
        ->and($rows[1]['class'])->toBe('指定席')
        ->and($rows[0]['fare_ticket_mode'])->toBe('same');
});

test("l'aperçu enregistre le billet de base dans le trajet du dossier sans toucher au reste", function () {
    $item = iwaItem(iwaFolder('F1', '2026-11-01', '2026-11-08'), [
        iwaRoute(['specific_requests' => 'Fenêtre', 'purchase_price' => 5000]),
        iwaRoute(['departure_date' => '2026-11-03']),
    ]);

    IwaOrders::persistFareTickets([
        ['item_id' => $item->id, 'route_index' => 0, 'fare_ticket_mode' => 'custom', 'fare_ticket_from' => 'Tokyo Station', 'fare_ticket_to' => 'Nagoya Station'],
        ['item_id' => $item->id, 'route_index' => 1, 'fare_ticket_mode' => 'none', 'fare_ticket_from' => 'Zzz'],
    ]);

    $routes = $item->fresh()->custom_values['transport_routes'];
    expect($routes[0])->toMatchArray([
        'fare_ticket_mode' => 'custom', 'fare_ticket_from' => 'Tokyo Station', 'fare_ticket_to' => 'Nagoya Station',
        'specific_requests' => 'Fenêtre', 'purchase_price' => 5000, 'train_number' => 'Nozomi 13',
    ])->and($routes[1]['fare_ticket_mode'])->toBe('none')
        ->and($routes[1])->not->toHaveKey('fare_ticket_from');
});

test("les clés fare_ticket_* survivent au recalcul des trajets côté agence et l'agence ne les voit pas", function () {
    $routes = [iwaRoute(['fare_ticket_mode' => 'custom', 'fare_ticket_from' => 'Tokyo Station', 'fare_ticket_to' => 'Nagoya Station'])];
    unset($routes[0]['option_id']); // pas de grille : withEstimates ne touche qu'à fare_estimate

    $after = app(TransportFareResolver::class)->withEstimates($routes);
    expect($after[0])->toMatchArray([
        'fare_ticket_mode' => 'custom', 'fare_ticket_from' => 'Tokyo Station', 'fare_ticket_to' => 'Nagoya Station',
    ]);

    // Les écrans agence ne créent que de nouvelles prestations et n'exposent jamais ces champs.
    foreach (['app/Filament/Agency/Pages/ViewProduct.php', 'app/Filament/Agency/Resources/AgencyFolderResource.php', 'resources/views/filament/agency/pages/view-product.blade.php'] as $file) {
        expect(file_get_contents(base_path($file)))->not->toContain('fare_ticket');
    }
});

test('ligne 2 de D en japonais : gare (name_ja sans 駅), arrêt de bus (name_ja) ou nom anglais court', function () {
    Schema::create('bus_stations', function (Blueprint $t) {
        $t->id();
        $t->string('name_en');
        $t->string('name_ja')->nullable();
        $t->timestamps();
    });
    \Illuminate\Support\Facades\DB::table('train_stations')->insert([['name_en' => 'Takayama Station', 'name_ja' => '高山駅'], ['name_en' => 'Nagoya Station', 'name_ja' => '名古屋駅']]);
    \Illuminate\Support\Facades\DB::table('bus_stations')->insert([['name_en' => 'Shirakawa-go', 'name_ja' => '白川郷'], ['name_en' => 'Unknown Stop', 'name_ja' => null]]);

    $d = fn (string $from, string $to) => iwaLoad([iwaRow(['departure' => $from, 'arrival' => $to, 'train_name' => 'Hida 6'])])->getSheet(0)->getCell('D5')->getValue();

    expect($d('Takayama Station', 'Nagoya Station'))->toBe("Hida 6\n高山 - 名古屋\n指定席\n乗車券 高山～名古屋")
        ->and(explode("\n", $d('Takayama Station', 'Shirakawa-go'))[1])->toBe('高山 - 白川郷')
        ->and(explode("\n", $d('Unknown Stop', 'Hakata Station (Gare Shinkansen)'))[1])->toBe('Unknown Stop - Hakata');
});
