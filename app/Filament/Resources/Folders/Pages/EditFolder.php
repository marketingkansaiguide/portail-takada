<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use App\Models\FolderHistory;

class EditFolder extends EditRecord
{
    protected static string $resource = FolderResource::class;
    public array $oldFormState = [];

    public function mount(int | string $record): void
    {
        parent::mount($record);
        $this->oldFormState = $this->form->getState(); // 📸 Capture AVANT modif
    }

    protected function getHeaderActions(): array { return [ Actions\DeleteAction::make() ]; }

    protected function afterSave(): void
    {
        $oldData = $this->oldFormState;
        $newData = $this->form->getState(); // 📸 Capture APRÈS modif

        // Réalignement des listes
        foreach (['folderItems', 'folderPassengers', 'contact_phones'] as $repeater) {
            if (isset($oldData[$repeater])) $oldData[$repeater] = array_values($oldData[$repeater]);
            if (isset($newData[$repeater])) $newData[$repeater] = array_values($newData[$repeater]);
        }

        $flatOld = Arr::dot($oldData);
        $flatNew = Arr::dot($newData);
        $changes = [];

        foreach ($flatNew as $key => $val) {
            $oldVal = $flatOld[$key] ?? null;
            if ($oldVal != $val) {
                $changes[$key] = ['old' => $oldVal, 'new' => $val];
            }
        }
        foreach ($flatOld as $key => $val) {
            if (!array_key_exists($key, $flatNew) && !empty($val)) {
                $changes[$key] = ['old' => $val, 'new' => null];
            }
        }

        // 🎯 Enregistrement dans TA table sur-mesure
        if (!empty($changes)) {
            FolderHistory::create([
                'folder_id' => $this->record->id,
                'user_id' => auth()->id(),
                'action' => 'Mise à jour',
                'changes_payload' => $changes,
            ]);
        }

        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record->id]));
    }
}