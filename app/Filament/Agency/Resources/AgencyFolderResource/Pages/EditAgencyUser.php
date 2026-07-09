<?php

namespace App\Filament\Agency\Resources\AgencyUserResource\Pages;

use App\Filament\Agency\Resources\AgencyUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgencyUser extends EditRecord
{
    protected static string $resource = AgencyUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    // Ramène à la liste après l'édition
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}