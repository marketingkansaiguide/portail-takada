<?php

namespace App\Filament\Agency\Resources\AgencyFolderResource\Pages;

use App\Filament\Agency\Resources\AgencyFolderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAgencyFolders extends ListRecords
{
    protected static string $resource = AgencyFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nouvelle Demande de Voyage'),
        ];
    }
}