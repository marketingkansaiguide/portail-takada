<?php

namespace App\Livewire;

use App\Models\Folder;
use App\Models\FolderMessage;
use Livewire\Component;
use Livewire\WithFileUploads; // 💡 Ajout pour les fichiers

class FolderChat extends Component
{
    use WithFileUploads;

    public Folder $folder;
    public string $newMessage = '';
    public $attachment; // 💡 Ajout pour le fichier

    public function mount(Folder $folder)
    {
        $this->folder = $folder;
    }

    public function sendMessage()
    {
        // 💡 Validation modifiée pour accepter un message OU un fichier
        $this->validate([
            'newMessage' => 'required_without:attachment|string|max:2000',
            'attachment' => 'nullable|file|max:10240', // Max 10Mo
        ]);

        $attachmentPath = null;
        if ($this->attachment) {
            $attachmentPath = $this->attachment->store('chat-attachments', 'public');
        }

        // On sauvegarde en base de données
        FolderMessage::create([
            'folder_id' => $this->folder->id,
            'user_id' => auth()->id(),
            'message' => $this->newMessage ?? '',
            'attachment_path' => $attachmentPath,
        ]);

        // On vide les champs
        $this->reset(['newMessage', 'attachment']);
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