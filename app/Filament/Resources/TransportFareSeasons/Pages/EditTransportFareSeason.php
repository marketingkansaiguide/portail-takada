<?php

namespace App\Filament\Resources\TransportFareSeasons\Pages;

use App\Filament\Resources\TransportFareSeasons\TransportFareSeasonResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransportFareSeason extends EditRecord
{
    protected static string $resource = TransportFareSeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
