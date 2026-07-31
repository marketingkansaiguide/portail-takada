<?php

namespace App\Filament\Agency\Resources\AgencyFolderResource\Pages;

use App\Filament\Agency\Resources\AgencyFolderResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class CreateAgencyFolder extends CreateRecord
{
    protected static string $resource = AgencyFolderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Filament::auth()->user() ?? auth()->user();
        $agencyId = $user?->agency_id;

        if (!$agencyId) {
            Notification::make()
                ->title('Agence non attribuée')
                ->body('Votre compte utilisateur n\'est rattaché à aucune agence. Veuillez contacter un administrateur.')
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }

        // 💡 Génération automatique d'une référence unique si non fournie
        if (empty($data['reference'])) {
            $data['reference'] = 'TKD-' . date('Ym') . '-' . strtoupper(Str::random(4));
        }

        $data['agency_id'] = $agencyId;
        $data['user_id'] = $user->id;
        $data['status'] = 'draft';

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