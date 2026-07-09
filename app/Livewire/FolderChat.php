<?php

namespace App\Livewire;

use App\Models\Folder;
use App\Models\FolderMessage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Filament\Facades\Filament;

class FolderChat extends Component
{
    use WithFileUploads;

    public Folder $folder;
    public string $newMessage = '';
    public $attachment;

    public function mount(Folder $folder)
    {
        $this->folder = $folder;
    }

    /**
     * 💡 MÉTHODE BLINDÉE : Identifie l'utilisateur selon le panel où il se trouve (Admin vs Agency)
     */
    protected function getActiveUserId()
    {
        // 1. On interroge Filament pour connaître le contexte actuel
        try {
            if ($panel = Filament::getCurrentPanel()) {
                return auth($panel->getAuthGuard())->id();
            }
        } catch (\Exception $e) {}

        // 2. Fallback robuste via le Referer (très utile pour les requêtes asynchrones Livewire)
        $referer = request()->headers->get('referer', '');
        if (str_contains($referer, '/admin') && auth('web')->check()) {
            return auth('web')->id();
        }
        if (auth('agency')->check()) {
            return auth('agency')->id();
        }

        // 3. Dernier recours
        return auth()->id();
    }

    public function sendMessage()
    {
        $this->validate([
            'newMessage' => 'required_without:attachment|string|max:2000',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $attachmentPath = null;
        if ($this->attachment) {
            $attachmentPath = $this->attachment->store('chat-attachments', 'public');
        }

        FolderMessage::create([
            'folder_id' => $this->folder->id,
            'user_id' => $this->getActiveUserId(), // 💡 Utilisation du bon ID !
            'message' => $this->newMessage ?? '',
            'attachment_path' => $attachmentPath,
        ]);

        $this->reset(['newMessage', 'attachment']);
        $this->folder->refresh();
    }

    public function render()
    {
        $messages = FolderMessage::where('folder_id', $this->folder->id)
            ->with('user')
            ->oldest()
            ->get();

        return view('livewire.folder-chat', compact('messages'));
    }
}