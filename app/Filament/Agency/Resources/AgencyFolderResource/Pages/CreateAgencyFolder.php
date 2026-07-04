<?php

namespace App\Filament\Agency\Resources\AgencyFolderResource\Pages;

use App\Filament\Agency\Resources\AgencyFolderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgencyFolder extends CreateRecord
{
    protected static string $resource = AgencyFolderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 💡 VERROUILLAGE SÉCURISÉ
        $data['agency_id'] = auth()->user()->agency_id; // L'agence globale (Partage de visibilité)
        $data['user_id'] = auth()->id(); // Le vendeur spécifique (Pour les futures notifications)
        $data['status'] = 'pending';
        
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