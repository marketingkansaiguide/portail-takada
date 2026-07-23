<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class FolderHistory extends Model
{
    protected $guarded = [];
    protected $casts = ['changes_payload' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function folder() { return $this->belongsTo(Folder::class); }

    /**
     * 💡 MOTEUR DE CONSOLIDATION 💡
     * Regroupe TOUTES les actions d'un même dossier et même utilisateur 
     * dans un intervalle de 15 secondes pour un seul rendu visuel.
     */
    public static function logConsolidated($folderId, $action, $summary)
    {
        if (!$folderId) return;

        $userId = auth()->id() ?? 1;
        
        $recent = self::where('folder_id', $folderId)
            ->where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subSeconds(15))
            ->latest('id')
            ->first();

        if ($recent) {
            $payload = is_string($recent->changes_payload) ? json_decode($recent->changes_payload, true) : $recent->changes_payload;
            if (!is_array($payload)) $payload = [];
            
            $existing = $payload['summary'] ?? '';
            
            // On évite les textes en double et on fusionne
            if (!str_contains($existing, trim($summary))) {
                $payload['summary'] = $existing . "\n\n---\n\n" . trim($summary);
                $recent->update([
                    'action' => 'Mise à jour globale', // Nom générique propre pour la fusion
                    'changes_payload' => $payload
                ]);
            }
        } else {
            self::create([
                'folder_id' => $folderId,
                'user_id' => $userId,
                'action' => $action,
                'changes_payload' => ['summary' => trim($summary)]
            ]);
        }
    }
}