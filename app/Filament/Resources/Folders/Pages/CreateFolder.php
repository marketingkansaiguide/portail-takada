<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use App\Models\FolderHistory;

class CreateFolder extends CreateRecord
{
    protected static string $resource = FolderResource::class;

    protected function afterCreate(): void
    {
        $newData = $this->form->getState();
        foreach (['folderItems', 'folderPassengers', 'contact_phones'] as $repeater) {
            if (isset($newData[$repeater])) $newData[$repeater] = array_values($newData[$repeater]);
        }

        $flatNew = Arr::dot($newData);
        $changes = [];
        foreach ($flatNew as $key => $val) {
            if (!empty($val)) {
                $changes[$key] = ['old' => 'Vide', 'new' => $val];
            }
        }

        if (!empty($changes)) {
            FolderHistory::create([
                'folder_id' => $this->record->id,
                'user_id' => auth()->id(),
                'action' => 'Création',
                'changes_payload' => $changes,
            ]);
        }
    }
}