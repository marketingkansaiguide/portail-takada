<?php

namespace App\Filament\Agency\Resources\AgencyFolderResource\Pages;

use App\Filament\Agency\Resources\AgencyFolderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgencyFolder extends EditRecord
{
    protected static string $resource = AgencyFolderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Sécurité lors de la mise à jour, on recalcule le prix exact des prestations demandées
        $totalItems = 0;
        if (isset($data['folderItems']) && is_array($data['folderItems'])) {
            foreach ($data['folderItems'] as $item) {
                $totalItems += (float) ($item['total_price'] ?? 0);
            }
        }
        $data['total_price'] = $totalItems;
        
        return $data;
    }
}