<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use App\Models\Folder;
use Filament\Resources\Pages\CreateRecord;

class CreateFolder extends CreateRecord
{
    protected static string $resource = FolderResource::class;

    /**
     * Ce formulaire crée un dossier agence (B2B), visible par l'agence émettrice.
     * L'origine est fixée côté serveur, jamais par un champ du formulaire.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['origin'] = Folder::ORIGIN_B2B;

        return $data;
    }
}
