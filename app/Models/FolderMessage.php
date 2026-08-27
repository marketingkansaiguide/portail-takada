<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FolderMessage extends Model
{
    use HasFactory;

    // 💡 AJOUT DE 'attachment_path' ICI POUR AUTORISER LA SAUVEGARDE !
    protected $fillable = [
        'folder_id',
        'user_id',
        'message',
        'attachment_path',
        'is_action_required',
    ];

    protected $casts = [
        'is_action_required' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (FolderMessage $message): void {
            $parts = [];

            if (filled($message->message)) {
                $parts[] = __('Message : :message', [
                    'message' => str($message->message)->squish()->limit(180),
                ]);
            }

            if (filled($message->attachment_path)) {
                $parts[] = __('Pièce jointe ajoutée : :file', [
                    'file' => basename($message->attachment_path),
                ]);
            }

            if ($message->is_action_required) {
                $parts[] = __('Le message nécessite une action.');
            }

            FolderHistory::logConsolidated(
                $message->folder_id,
                __('Nouveau message'),
                implode("\n", $parts) ?: __('Un message vide a été enregistré.'),
                'communication',
                [],
                ['message_id' => $message->id, 'event' => 'created'],
            );
        });

        static::updated(function (FolderMessage $message): void {
            if (!$message->wasChanged('is_action_required')) {
                return;
            }

            FolderHistory::logConsolidated(
                $message->folder_id,
                __('Suivi du message'),
                $message->is_action_required
                    ? __('Le message a été marqué comme nécessitant une action.')
                    : __('Le message ne nécessite plus d’action.'),
                'communication',
                [[
                    'field' => 'is_action_required',
                    'label' => __('Action requise'),
                    'old' => $message->getOriginal('is_action_required') ? __('Oui') : __('Non'),
                    'new' => $message->is_action_required ? __('Oui') : __('Non'),
                ]],
                ['message_id' => $message->id, 'event' => 'updated'],
            );
        });
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
