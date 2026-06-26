<?php

namespace App\Filament\Resources\ClientGroups\Pages;

use App\Filament\Resources\ClientGroups\ClientGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClientGroup extends EditRecord
{
    protected static string $resource = ClientGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
