<?php

namespace App\Filament\Agency\Resources\AgencyFolderResource\Pages;

use App\Filament\Agency\Resources\AgencyFolderResource;
use App\Models\User;
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
                ->title(__('Agence non attribuée'))
                    ->body(__('Votre compte utilisateur n\'est rattaché à aucune agence. Veuillez contacter un administrateur.'))
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
        $data['main_seller_id'] = User::query()
            ->whereKey($data['main_seller_id'] ?? $user->id)
            ->where('agency_id', $agencyId)
            ->where('role', User::ROLE_AGENCY)
            ->value('id') ?? $user->id;

        $totalItems = \App\Models\FolderItem::sumBillableFormItems($data['folderItems'] ?? []);
        $data['total_price'] = $totalItems;

        return $data;
    }
}
