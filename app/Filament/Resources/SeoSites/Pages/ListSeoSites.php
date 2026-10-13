<?php

namespace App\Filament\Resources\SeoSites\Pages;

use App\Filament\Resources\SeoSites\SeoSiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeoSites extends ListRecords
{
    protected static string $resource = SeoSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
