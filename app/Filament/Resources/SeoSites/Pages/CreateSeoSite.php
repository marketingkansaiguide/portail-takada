<?php

namespace App\Filament\Resources\SeoSites\Pages;

use App\Filament\Resources\SeoSites\SeoSiteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSeoSite extends CreateRecord
{
    protected static string $resource = SeoSiteResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
