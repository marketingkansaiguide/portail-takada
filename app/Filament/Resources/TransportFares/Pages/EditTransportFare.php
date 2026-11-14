<?php

namespace App\Filament\Resources\TransportFares\Pages;

use App\Filament\Resources\TransportFares\TransportFareResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransportFare extends EditRecord
{
    protected static string $resource = TransportFareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
