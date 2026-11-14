<?php

namespace App\Filament\Resources\TransportFareSeasons\Pages;

use App\Filament\Resources\TransportFareSeasons\TransportFareSeasonResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransportFareSeasons extends ListRecords
{
    protected static string $resource = TransportFareSeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
