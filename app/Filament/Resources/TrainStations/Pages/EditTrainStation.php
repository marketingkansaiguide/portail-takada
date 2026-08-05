<?php

namespace App\Filament\Resources\TrainStations\Pages;

use App\Filament\Resources\TrainStations\TrainStationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrainStation extends EditRecord
{
    protected static string $resource = TrainStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}