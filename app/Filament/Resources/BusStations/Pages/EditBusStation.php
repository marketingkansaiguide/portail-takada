<?php

namespace App\Filament\Resources\BusStations\Pages;

use App\Filament\Resources\BusStations\BusStationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBusStation extends EditRecord
{
    protected static string $resource = BusStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}