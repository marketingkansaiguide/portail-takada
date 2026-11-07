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

        $data['origin'] = \App\Models\Folder::ORIGIN_B2B;
        $data['agency_id'] = $agencyId;
        $data['user_id'] = $user->id;
        $data['status'] = 'draft';
        $data['main_seller_id'] = User::query()
            ->whereKey($data['main_seller_id'] ?? $user->id)
            ->where('agency_id', $agencyId)
            ->where('role', User::ROLE_AGENCY)
            ->value('id') ?? $user->id;

        // Les frais viennent du groupe client de l'agence, jamais du champ caché envoyé par le navigateur.
        $data['folder_fee'] = (float) ($user->agency?->clientGroup?->folder_fee ?? 0);
        // Un nouveau dossier n'a pas encore de prestation : son total correspond aux frais de dossier.
        $data['total_price'] = $data['folder_fee'];

        return $data;
    }
}
