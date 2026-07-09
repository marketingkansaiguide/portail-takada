<?php

namespace App\Filament\Agency\Resources\AgencyUserResource\Pages;

use App\Filament\Agency\Resources\AgencyUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgencyUser extends CreateRecord
{
    protected static string $resource = AgencyUserResource::class;
    
    // Ramène à la liste après la création
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}