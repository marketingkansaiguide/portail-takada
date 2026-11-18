<?php

use App\Filament\Pages\IwaOrders;
use App\Models\Folder;
use App\Models\FolderItem;
use App\Support\IwaPdfExport;
use App\Support\IwaRowFormatter;
use App\Support\TransportFareResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

beforeEach(fn () => iwaSchema());

test('cellule D : modes same, custom et none', function () {
    $ja = ['Tokyo Station' => '東京駅', 'Kyoto Station' => '京都駅', 'Nagoya Station' => '名古屋駅'];

    expect(IwaRowFormatter::detailCell(iwaRow(), $ja))
        ->toBe("Nozomi 13\n東京 - 京都\n指定席\n乗車券東京～京都");

    expect(IwaRowFormatter::detailCell(iwaRow([
        'fare_ticket_mode' => 'custom', 'fare_ticket_from' => 'Tokyo Station', 'fare_ticket_to' => 'Nagoya Station',
        'specific_requests' => 'Côté Mont Fuji',
    ]), $ja))->toBe("Nozomi 13\n東京 - 京都\n指定席－Côté Mont Fuji\n乗車券東京～名古屋");

    expect(IwaRowFormatter::detailCell(iwaRow(['fare_ticket_mode' => 'none']), $ja))
        ->toBe("Nozomi 13\n東京 - 京都\n指定席");

    // Clé absente = « même trajet » ; gare sans name_ja (arrêt de bus) = nom anglais court.
    $row = iwaRow(['arrival' => 'Takayama Bus Terminal (Nohi)']);
    unset($row['fare_ticket_mode']);
    expect(IwaRowFormatter::detailCell($row, $ja))->toEndWith('乗車券東京～Takayama Bus Terminal');
});

test('noms de gares raccourcis en anglais court', function () {
    expect(IwaRowFormatter::shortName('Hakata Station (Gare Shinkansen)'))->toBe('Hakata')
        ->and(IwaRowFormatter::shortName('Shin-Osaka Sta.'))->toBe('Shin-Osaka')
        ->and(IwaRowFormatter::shortName('  Kyoto   Station '))->toBe('Kyoto');
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


function iwaHtml(array $rows, ?Folder $folder = null): string
{
    return IwaPdfExport::renderHtml(collect($rows), $folder);
}

test('voyageurs : nombre seul sans enfant payant, « 大 n / 小 m » avec un enfant', function () {
    expect(IwaRowFormatter::passengersCell(iwaRow()))->toBe(2)
        ->and(IwaRowFormatter::passengersCell(iwaRow(['pax_count' => 3, 'children_count' => 1])))->toBe('大　2 / 小　1');
});

test('le HTML du PDF contient gares japonaises, 乗車券, date japonaise et en-têtes', function () {
    \Illuminate\Support\Facades\DB::table('train_stations')->insert([
        ['name_en' => 'Tokyo Station', 'name_ja' => '東京駅'], ['name_en' => 'Kyoto Station', 'name_ja' => '京都駅'],
    ]);
    $folder = iwaFolder('Voyage Test', '2026-10-28', '2026-11-05');
    $html = iwaHtml([iwaRow(['folder_id' => $folder->id, 'pax_count' => 3, 'children_count' => 1])], $folder);

    expect($html)->toContain('TAKADA TRAVEL合同会社')
        ->toContain('Voyage Test')->toContain('Dupont様')
        ->toContain('2026年10月28日')->toContain('2026年11月5日')->toContain('2026年11月2日')
        ->toContain('日程')->toContain('列車名/区間/<br>設備/乗車券')->toContain('出発時刻')
        ->toContain('Nozomi 13')->toContain('東京 - 京都')->toContain('指定席')->toContain('乗車券東京～京都')
        ->toContain('09:30')->toContain('11:49')->toContain('大　2 / 小　1');
});

test('les trajets du PDF sont triés par date puis par heure', function () {
    $html = iwaHtml([
        iwaRow(['date' => '2026-11-04', 'train_name' => 'TrainC']),
        iwaRow(['date' => '2026-11-02', 'dep_time' => '14:00', 'train_name' => 'TrainB']),
        iwaRow(['date' => '2026-11-02', 'dep_time' => '8:05', 'train_name' => 'TrainA']),
    ]);

    expect(strpos($html, 'TrainA'))->toBeLessThan(strpos($html, 'TrainB'))
        ->and(strpos($html, 'TrainB'))->toBeLessThan(strpos($html, 'TrainC'))
        ->and($html)->toContain('08:05');
});

test('nom de fichier : référence ou id, voyageur translittéré en ASCII', function () {
    expect(IwaPdfExport::fileName('TKD-202611-AB12', 'Dupont'))->toBe('IWA_TKD-202611-AB12_Dupont.pdf')
        ->and(IwaPdfExport::fileName('7', 'José Müller-Łukasz'))->toBe('IWA_7_Jose-Muller-Lukasz.pdf')
        ->and(IwaPdfExport::fileName('7', '山田'))->toStartWith('IWA_7_')->toEndWith('.pdf')
        ->and(IwaPdfExport::fileName('7', '   '))->toBe('IWA_7_client.pdf');
});

test('deux dossiers donnent un ZIP de deux PDF aux bons noms', function () {
    $a = iwaFolder('A', '2026-11-01', '2026-11-08', 'Dupont');
    $b = iwaFolder('B', '2026-11-03', '2026-11-09', 'Martin');

    $response = IwaPdfExport::download([
        iwaRow(['folder_id' => $a->id, 'pax_name' => 'Dupont']),
        iwaRow(['folder_id' => $b->id, 'folder_start' => '2026-11-03', 'pax_name' => 'Martin']),
        iwaRow(['folder_id' => $a->id, 'pax_name' => 'Dupont', 'date' => '2026-11-05']),
    ]);

    $path = $response->getFile()->getPathname();
    expect($response->getFile()->getFilename())->not->toBeEmpty();
    $zip = new ZipArchive();
    expect($zip->open($path))->toBeTrue();
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
        expect(substr($zip->getFromIndex($i), 0, 5))->toBe('%PDF-');
    }
    $zip->close();

    expect($names)->toBe(["IWA_{$a->id}_Dupont.pdf", "IWA_{$b->id}_Martin.pdf"])
        ->and($response->headers->get('content-disposition'))->toContain('Commande_IWA_')->toContain('.zip');
    @unlink($path);
});

test('un seul dossier donne directement un PDF, sans ZIP', function () {
    $a = iwaFolder('A', '2026-11-01', '2026-11-08', 'Dupont');

    $response = IwaPdfExport::download([iwaRow(['folder_id' => $a->id])]);
    $path = $response->getFile()->getPathname();

    expect($response->headers->get('content-disposition'))->toContain("IWA_{$a->id}_Dupont.pdf")
        ->and(substr(file_get_contents($path), 0, 5))->toBe('%PDF-');
    @unlink($path);
});

test('le PDF généré embarque la police japonaise (gras compris)', function () {
    $a = iwaFolder('A', '2026-11-01', '2026-11-08');
    $pdf = IwaPdfExport::pdfBinary(collect([iwaRow(['folder_id' => $a->id])]), $a);

    expect($pdf)->toContain('BIZUDPGothic');
});

test('ligne 2 en japonais : gare (name_ja sans 駅), arrêt de bus (name_ja) ou nom anglais court', function () {
    Schema::create('bus_stations', function (Blueprint $t) {
        $t->id();
        $t->string('name_en');
        $t->string('name_ja')->nullable();
        $t->timestamps();
    });
    \Illuminate\Support\Facades\DB::table('train_stations')->insert([['name_en' => 'Takayama Station', 'name_ja' => '高山駅'], ['name_en' => 'Nagoya Station', 'name_ja' => '名古屋駅']]);
    \Illuminate\Support\Facades\DB::table('bus_stations')->insert([['name_en' => 'Shirakawa-go', 'name_ja' => '白川郷'], ['name_en' => 'Unknown Stop', 'name_ja' => null]]);

    expect(iwaHtml([iwaRow(['departure' => 'Takayama Station', 'arrival' => 'Nagoya Station', 'train_name' => 'Hida 6'])]))
        ->toContain('高山 - 名古屋')->toContain('乗車券高山～名古屋')
        ->and(iwaHtml([iwaRow(['departure' => 'Takayama Station', 'arrival' => 'Shirakawa-go'])]))->toContain('高山 - 白川郷')
        ->and(iwaHtml([iwaRow(['departure' => 'Unknown Stop', 'arrival' => 'Hakata Station (Gare Shinkansen)'])]))->toContain('Unknown Stop - Hakata');
});
