<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Folder;
use App\Models\FolderMessage;
use App\Models\FolderTask;
use App\Models\FolderHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ActionBoardWidget extends Widget
{
    protected string $view = 'filament.widgets.action-board-widget';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 1;

    // 💡 Déclenché automatiquement à l'affichage du tableau de bord
    public function mount()
    {
        $this->generateMissingTasks();
    }

    /**
     * Scanne les dossiers et génère les tâches si elles n'existent pas déjà.
     */
    private function generateMissingTasks()
    {
        $folders = Folder::with(['folderItems.product'])->whereNotIn('status', ['cancelled', 'completed'])->get();

        foreach ($folders as $folder) {
            // 1. Nouveau dossier
            if (in_array($folder->status, ['pending', 'new', 'draft'])) {
                $this->createTaskIfMissing($folder->id, "new_folder_{$folder->id}", "Nouveau dossier à analyser et deviser.", 'heroicon-m-document-plus', 'text-blue-500');
            }

            // 2. Nouveau message de l'agence non lu
            $lastMsg = FolderMessage::where('folder_id', $folder->id)->latest()->first();
            if ($lastMsg && $lastMsg->sender_type === \App\Models\Agency::class && !$lastMsg->is_read) {
                $this->createTaskIfMissing($folder->id, "unread_msg_{$lastMsg->id}", "Nouveau message de l'agence.", 'heroicon-m-chat-bubble-left-ellipsis', 'text-amber-500');
            }

            // 3. Prestations et Ouvertures des ventes
            foreach ($folder->folderItems as $item) {
                if ($item->item_status_id === 5) { // 💡 ID de statut d'alerte (à adapter)
                    $this->createTaskIfMissing($folder->id, "item_alert_{$item->id}", "Action requise sur la prestation : {$item->name}.", 'heroicon-m-exclamation-triangle', 'text-red-500');
                }

                if ($item->product && $item->status === 'pending') {
                    $delay = $item->product->booking_open_delay ?? null;
                    if ($delay !== null) {
                        $openDate = Carbon::parse($item->service_date)->subDays($delay);
                        if (now()->greaterThanOrEqualTo($openDate)) {
                            $this->createTaskIfMissing($folder->id, "booking_open_{$item->id}", "Les réservations sont ouvertes pour : {$item->name}.", 'heroicon-m-calendar-days', 'text-emerald-500');
                        }
                    }
                }
            }
        }
    }

    private function createTaskIfMissing($folderId, $code, $desc, $icon, $color)
    {
        FolderTask::firstOrCreate(
            ['action_code' => $code], // Si ce code existe (même déjà coché), il ne le recrée pas !
            [
                'folder_id' => $folderId,
                'description' => $desc,
                'icon' => $icon,
                'color' => $color,
                'is_completed' => false,
            ]
        );
    }

    // 💡 Récupère les tâches non cochées, groupées par dossier
    public function getPendingTasksByFolderProperty()
    {
        return FolderTask::where('is_completed', false)
            ->with(['folder.agency'])
            ->get()
            ->groupBy('folder_id');
    }

    // 💡 L'action quand l'agent clique sur le bouton
    public function markAsDone($taskId)
    {
        $task = FolderTask::find($taskId);
        
        if ($task && !$task->is_completed) {
            // 1. On ferme la tâche
            $task->update([
                'is_completed' => true,
                'completed_by' => Auth::id(),
                'completed_at' => now(),
            ]);

            // 2. On l'inscrit dans l'historique officiel du dossier
            FolderHistory::create([
                'folder_id' => $task->folder_id,
                'user_id' => Auth::id(),
                'action' => "Tâche traitée",
                'details' => "Action effectuée : " . $task->description,
            ]);
        }
    }
}