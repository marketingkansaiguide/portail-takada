<?php

use App\Models\FolderItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('specific requests stay attached to each route after saving and are tracked in history', function () {
    config(['database.connections.transport_test' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]]);

    Schema::connection('transport_test')->create('folder_items', function (Blueprint $table) {
        $table->id();
        $table->text('custom_values')->nullable();
        $table->timestamps();
    });

    $routes = [
        ['departure_station' => 'Tokyo', 'arrival_station' => 'Kyoto', 'specific_requests' => "Siège côté fenêtre\nBagage volumineux"],
        ['departure_station' => 'Kyoto', 'arrival_station' => 'Osaka', 'specific_requests' => 'Places côte à côte'],
        ['departure_station' => 'Osaka', 'arrival_station' => 'Nara'],
    ];
    $item = new FolderItem(['custom_values' => ['transport_routes' => $routes]]);
    $item->setConnection('transport_test');
    FolderItem::withoutEvents(fn () => $item->save());

    expect($item->fresh()->custom_values['transport_routes'])->toBe($routes);

    $updatedRoutes = $routes;
    $updatedRoutes[1]['specific_requests'] = 'Accès PMR';
    $changes = FolderItem::formatTransportRouteHistoryChanges($routes, $updatedRoutes);

    expect($changes)->toHaveCount(1)
        ->and($changes[0]['field'])->toBe('custom_values.transport_routes.1.specific_requests')
        ->and($changes[0]['old'])->toBe('Places côte à côte')
        ->and($changes[0]['new'])->toBe('Accès PMR');
});
