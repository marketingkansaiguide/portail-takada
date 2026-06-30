<?php

namespace App\Filament\Resources\ItemStatusResource\Pages;

use App\Filament\Resources\ItemStatusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemStatuses extends ListRecords
{
    protected static string $resource = ItemStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}