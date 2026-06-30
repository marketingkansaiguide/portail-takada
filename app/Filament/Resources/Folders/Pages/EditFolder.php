<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use App\Models\Folder;

class EditFolder extends EditRecord
{
    protected static string $resource = FolderResource::class;
    
    // 📸 Stockage de l'image de la base de données avant modifs
    protected array $dbSnapshot = [];

    protected function getHeaderActions(): array
    {
        return [ Actions\DeleteAction::make() ];
    }

    protected function beforeSave(): void
    {
        // 🎯 1. Capture BDD AVANT la sauvegarde
        $this->dbSnapshot = Folder::with(['folderItems', 'folderPassengers'])
            ->find($this->record->id)
            ->toArray();
    }

    protected function afterSave(): void
    {
        // 🎯 2. Capture BDD APRÈS la sauvegarde par Filament
        $newData = Folder::with(['folderItems', 'folderPassengers'])
            ->find($this->record->id)
            ->toArray();

        $changesNew = [];
        $changesOld = [];

        // Transformation en liste simple (ex: folderItems.0.quantity)
        $flatOld = Arr::dot($this->dbSnapshot);
        $flatNew = Arr::dot($newData);

        // 🎯 3. Détection des changements et ajouts
        foreach ($flatNew as $key => $val) {
            if (str_contains($key, 'updated_at') || str_contains($key, 'created_at')) continue;

            $oldVal = $flatOld[$key] ?? null;
            if ($oldVal !== $val) {
                $changesNew[$key] = $val;
                $changesOld[$key] = $oldVal;
            }
        }

        // 🎯 4. Détection des suppressions
        foreach ($flatOld as $key => $val) {
            if (str_contains($key, 'updated_at') || str_contains($key, 'created_at')) continue;

            if (!array_key_exists($key, $flatNew)) {
                $changesNew[$key] = null;
                $changesOld[$key] = $val;
            }
        }

        // 🎯 5. Forçage de l'historique si on a trouvé une différence
        if (!empty($changesNew)) {
            $activity = activity()
                ->performedOn($this->record)
                ->causedBy(auth()->user())
                ->log('updated');
            
            $activity->attribute_changes = ['attributes' => $changesNew, 'old' => $changesOld];
            $activity->save();
        }

        // 🎯 6. Rechargement de la page pour rafraîchir le tableau !
        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record->id]));
    }
}