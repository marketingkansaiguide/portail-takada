<?php

namespace App\Filament\Agency\Resources\AgencyUserResource\Pages;

use App\Filament\Agency\Resources\AgencyUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAgencyUsers extends ListRecords
{
    protected static string $resource = AgencyUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Ajouter un vendeur')
                ->icon('heroicon-o-plus'),
        ];
    }
}