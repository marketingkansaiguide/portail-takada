<?php

namespace App\Filament\Resources\BusStations\Pages;

use App\Filament\Resources\BusStations\BusStationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBusStation extends CreateRecord
{
    protected static string $resource = BusStationResource::class;
}