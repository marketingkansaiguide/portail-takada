<?php

namespace App\Filament\Resources\ItemStatusResource\Pages;

use App\Filament\Resources\ItemStatusResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItemStatus extends EditRecord
{
    protected static string $resource = ItemStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}