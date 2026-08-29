<?php

namespace App\Filament\Agency\Resources\AgencyUserResource\Pages;

use App\Filament\Agency\Resources\AgencyUserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditAgencyUser extends EditRecord
{
    protected static string $resource = AgencyUserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['role'] = User::ROLE_AGENCY;
        $data['agency_id'] = Filament::auth()->user()?->agency_id;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
