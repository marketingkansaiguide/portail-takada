<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use App\Models\Folder;

class CreateFolder extends CreateRecord
{
    protected static string $resource = FolderResource::class;

    protected function afterCreate(): void
    {
        // 📸 On enregistre l'état initial complet du dossier fraîchement créé
        $newData = Folder::with(['folderItems', 'folderPassengers'])
            ->find($this->record->id)
            ->toArray();

        $flatNew = Arr::dot($newData);
        
        foreach ($flatNew as $key => $val) {
            if (str_contains($key, 'updated_at') || str_contains($key, 'created_at')) {
                unset($flatNew[$key]);
            }
        }

        $activity = activity()
            ->performedOn($this->record)
            ->causedBy(auth()->user())
            ->log('created');
        
        $activity->attribute_changes = ['attributes' => $flatNew, 'old' => []];
        $activity->save();
    }
}