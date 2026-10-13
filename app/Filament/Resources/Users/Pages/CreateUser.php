<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // users.role a une valeur par défaut "admin" : on la fixe toujours explicitement.
        $data['role'] = User::primaryRoleFor((array) ($this->data['roles'] ?? [])) ?? User::ROLE_AGENT;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncPrimaryRole();
    }
}
