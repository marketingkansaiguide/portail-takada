<?php

namespace App\Livewire;

use App\Models\Folder;
use App\Models\FolderMessage;
use Livewire\Component;

class FolderChat extends Component
{
    public Folder $folder;
    public string $newMessage = '';

    public function mount(Folder $folder)
    {
        $this->folder = $folder;
    }

    public function sendMessage()
    {
        // On vérifie que le message n'est pas vide
        $this->validate([
            'newMessage' => 'required|string|max:2000',
        ]);

        // On sauvegarde en base de données
        FolderMessage::create([
            'folder_id' => $this->folder->id,
            'user_id' => auth()->id(),
            'message' => $this->newMessage,
        ]);

        // On vide le champ texte
        $this->newMessage = '';
    }

    public function render()
    {
        // On récupère tous les messages du dossier, du plus ancien au plus récent
        $messages = FolderMessage::where('folder_id', $this->folder->id)
            ->with('user')
            ->oldest()
            ->get();

        return view('livewire.folder-chat', compact('messages'));
    }
}