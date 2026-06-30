<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class FolderPassenger extends Model
{
    protected $guarded = [];

    /**
     * Écoute des événements du modèle pour forcer l'historique du dossier
     */
    protected static function booted()
    {
        $getPassengerName = function ($passenger) {
            $name = trim(($passenger->first_name ?? '') . ' ' . ($passenger->last_name ?? ''));
            return $name ?: ($passenger->name ?? 'Un passager');
        };

        // 1. On utilise 'updated'
        static::updated(function ($passenger) use ($getPassengerName) {
            // 2. Verrou mémoire anti-spam
            static $processedUpdates = [];

            if ($passenger->wasChanged()) {
                $changes = $passenger->getChanges();
                unset($changes['updated_at']);

                if (!empty($changes)) {
                    // Création de l'empreinte de modification
                    $fingerprint = $passenger->id . '_' . md5(json_encode($changes));
                    
                    if (isset($processedUpdates[$fingerprint])) {
                        return; // Bloque l'événement doublon de Filament
                    }
                    $processedUpdates[$fingerprint] = true;

                    $changesText = [];
                    
                    $labels = [
                        'first_name' => 'Prénom',
                        'last_name' => 'Nom',
                        'birth_date' => 'Date de naissance',
                        'nationality' => 'Nationalité',
                        'dietary_restrictions' => 'Restrictions alimentaires',
                        'mobility_concerns' => 'Problèmes de mobilité',
                    ];

                    foreach ($changes as $key => $newValue) {
                        if (!array_key_exists($key, $labels)) continue;

                        $oldValue = $passenger->getOriginal($key);

                        if ($key === 'birth_date') {
                            $oldDate = $oldValue ? Carbon::parse($oldValue)->format('d/m/Y') : 'Non renseignée';
                            $newDate = $newValue ? Carbon::parse($newValue)->format('d/m/Y') : 'Vide';
                            $changesText[] = "• {$labels[$key]} : '{$oldDate}' ➔ '{$newDate}'";
                            continue;
                        }

                        $oldString = $oldValue !== null && $oldValue !== '' ? (string)$oldValue : 'Non renseigné';
                        $newString = $newValue !== null && $newValue !== '' ? (string)$newValue : 'Vide';
                        $changesText[] = "• {$labels[$key]} : '{$oldString}' ➔ '{$newString}'";
                    }

                    if (!empty($changesText)) {
                        $summary = "Les informations du passager '{$getPassengerName($passenger)}' ont été modifiées :\n" . implode("\n", $changesText);

                        \App\Models\FolderHistory::create([
                            'folder_id' => $passenger->folder_id,
                            'user_id' => auth()->id(),
                            'action' => 'Mise à jour Passager',
                            'changes_payload' => [
                                'summary' => $summary
                            ]
                        ]);
                    }
                }
            }
        });

        static::created(function ($passenger) use ($getPassengerName) {
            static $processedCreations = [];
            if (isset($processedCreations[$passenger->id])) return;
            $processedCreations[$passenger->id] = true;

            \App\Models\FolderHistory::create([
                'folder_id' => $passenger->folder_id,
                'user_id' => auth()->id(),
                'action' => 'Ajout Passager',
                'changes_payload' => [
                    'summary' => "Le passager '{$getPassengerName($passenger)}' a été ajouté au dossier."
                ]
            ]);
        });

        static::deleted(function ($passenger) use ($getPassengerName) {
            \App\Models\FolderHistory::create([
                'folder_id' => $passenger->folder_id,
                'user_id' => auth()->id(),
                'action' => 'Suppression Passager',
                'changes_payload' => [
                    'summary' => "Le passager '{$getPassengerName($passenger)}' a été retiré du dossier."
                ]
            ]);
        });
    }

    public function folder(): BelongsTo { return $this->belongsTo(Folder::class); }
}