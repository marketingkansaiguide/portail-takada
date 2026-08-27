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
                    $structuredChanges = [];
                    
                    $labels = [
                        'first_name' => __('Prénom'),
                        'last_name' => __('Nom'),
                        'birth_date' => __('Date de naissance'),
                        'nationality' => __('Nationalité'),
                        'dietary_restrictions' => __('Restrictions alimentaires'),
                        'mobility_concerns' => __('Problèmes de mobilité'),
                    ];

                    foreach ($changes as $key => $newValue) {
                        if (!array_key_exists($key, $labels)) continue;

                        $oldValue = $passenger->getOriginal($key);

                        if ($key === 'birth_date') {
                            $oldDate = $oldValue ? Carbon::parse($oldValue)->format('d/m/Y') : __('Non renseignée');
                            $newDate = $newValue ? Carbon::parse($newValue)->format('d/m/Y') : __('Vide');
                            $changesText[] = "• {$labels[$key]} : '{$oldDate}' ➔ '{$newDate}'";
                            $structuredChanges[] = ['field' => $key, 'label' => $labels[$key], 'old' => $oldDate, 'new' => $newDate];
                            continue;
                        }

                        $oldString = $oldValue !== null && $oldValue !== '' ? (string)$oldValue : __('Non renseigné');
                        $newString = $newValue !== null && $newValue !== '' ? (string)$newValue : __('Vide');
                        $changesText[] = "• {$labels[$key]} : '{$oldString}' ➔ '{$newString}'";
                        $structuredChanges[] = ['field' => $key, 'label' => $labels[$key], 'old' => $oldString, 'new' => $newString];
                    }

                    if (!empty($changesText)) {
                        $summary = __('Les informations du passager :name ont été modifiées :', ['name' => $getPassengerName($passenger)]) . "\n" . implode("\n", $changesText);

                        FolderHistory::logConsolidated(
                            $passenger->folder_id,
                            __('Mise à jour Passager'),
                            $summary,
                            'passenger',
                            $structuredChanges,
                            ['passenger_id' => $passenger->id, 'event' => 'updated'],
                        );
                    }
                }
            }
        });

        static::created(function ($passenger) use ($getPassengerName) {
            static $processedCreations = [];
            if (isset($processedCreations[$passenger->id])) return;
            $processedCreations[$passenger->id] = true;

            FolderHistory::logConsolidated(
                $passenger->folder_id,
                __('Ajout Passager'),
                __('Le passager :name a été ajouté au dossier.', ['name' => $getPassengerName($passenger)]),
                'passenger',
                [],
                ['passenger_id' => $passenger->id, 'event' => 'created'],
            );
        });

        static::deleted(function ($passenger) use ($getPassengerName) {
            FolderHistory::logConsolidated(
                $passenger->folder_id,
                __('Suppression Passager'),
                __('Le passager :name a été retiré du dossier.', ['name' => $getPassengerName($passenger)]),
                'passenger',
                [],
                ['passenger_id' => $passenger->id, 'event' => 'deleted'],
            );
        });
    }

    public function folder(): BelongsTo { return $this->belongsTo(Folder::class); }
}
