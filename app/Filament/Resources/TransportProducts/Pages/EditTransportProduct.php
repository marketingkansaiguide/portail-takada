<?php

namespace App\Filament\Resources\TransportProducts\Pages;

use App\Filament\Resources\TransportProducts\TransportProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransportProduct extends EditRecord
{
    protected static string $resource = TransportProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}