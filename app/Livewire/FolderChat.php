<?php

namespace App\Livewire;

use App\Models\Folder;
use App\Models\FolderMessage;
use App\Models\User;
use App\Mail\NewChatMessageForAgencyMail;
use App\Mail\NewChatMessageForAdminMail;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithFileUploads;
use Filament\Facades\Filament;

class FolderChat extends Component
{
    use WithFileUploads;

    public Folder $folder;
    public string $newMessage = '';
    public $attachment;
    
    // Ajout de la variable pour la case à cocher
    public bool $isActionRequired = false;

    public function mount(Folder $folder)
    {
        $this->folder = $folder;
    }

    /**
     * Identifie l'utilisateur selon le panel où il se trouve (Admin vs Agency)
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

        $userId = $this->getActiveUserId();

        FolderMessage::create([
            'folder_id' => $this->folder->id,
            'user_id' => $userId, 
            'message' => $this->newMessage ?? '',
            'attachment_path' => $attachmentPath,
            'is_action_required' => $this->isActionRequired, // Enregistrement du statut Action Requise
        ]);

        // IDENTIFICATION DU RÔLE DE L'EXPÉDITEUR ET ENVOI DE L'EMAIL
        $sender = User::find($userId);
        
        if ($sender) {
            // Si c'est un ADMIN Takada qui écrit
            if (in_array($sender->role, ['super_admin', 'admin'])) {
                // On envoie le mail au Vendeur Principal (l'agence)
                if ($this->folder->mainSeller && $this->folder->mainSeller->email) {
                    Mail::to($this->folder->mainSeller->email)->send(new NewChatMessageForAgencyMail($this->folder));
                }
            } 
            // Si c'est l'AGENCE qui écrit
            else {
                $setting = \App\Models\Setting::first();
                $adminEmails = [];
                
                // On récupère la liste des e-mails et on la transforme en tableau (array)
                if ($setting && !empty($setting->admin_email_notifications)) {
                    $adminEmails = explode(',', $setting->admin_email_notifications);
                    $adminEmails = array_map('trim', $adminEmails);
                    $adminEmails = array_filter($adminEmails);
                }
                
                // Si aucune adresse n'est configurée, on utilise celle du .env par défaut
                if (empty($adminEmails)) {
                    $adminEmails = [env('MAIL_ADMIN_RECEIVER', env('MAIL_FROM_ADDRESS'))];
                }
                
                // Envoi à toutes les adresses récoltées
                if (!empty($adminEmails)) {
                    Mail::to($adminEmails)->send(new NewChatMessageForAdminMail($this->folder));
                }
            }
        }

        $this->reset(['newMessage', 'attachment', 'isActionRequired']);
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