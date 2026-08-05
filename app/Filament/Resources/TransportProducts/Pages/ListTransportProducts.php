<?php

namespace App\Filament\Resources\TransportProducts\Pages;

use App\Filament\Resources\TransportProducts\TransportProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransportProducts extends ListRecords
{
    protected static string $resource = TransportProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}