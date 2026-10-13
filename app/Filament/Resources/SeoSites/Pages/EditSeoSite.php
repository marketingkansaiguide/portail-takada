<?php

namespace App\Filament\Resources\SeoSites\Pages;

use App\Filament\Resources\SeoSites\SeoSiteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSeoSite extends EditRecord
{
    protected static string $resource = SeoSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
