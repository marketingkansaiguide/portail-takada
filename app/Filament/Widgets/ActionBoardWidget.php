<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

use App\Models\Folder;
use App\Models\FolderItem;
use App\Models\FolderMessage;
use App\Models\FolderTask;
use App\Models\FolderHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;

class ActionBoardWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.widgets.action-board-widget';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 1;

    public function mount()
    {
        // Géré dynamiquement par #[Computed]
    }

    private function generateMissingTasks()
    {
        $activeTasks = FolderTask::where('is_completed', false)->get();
        foreach ($activeTasks as $task) {
            if (preg_match('/^(manage_item|item_alert|invoice_missing|booking_open|missing_purchase_price)_(\d+)$/', $task->action_code, $matches)) {
                $itemId = $matches[2];
                if (!FolderItem::find($itemId)) {
                    $task->delete();
                }
            }
        }

        $folders = Folder::with(['folderItems.product', 'folderItems.itemStatus', 'agency'])
            ->whereNotIn('status', ['cancelled'])
            ->get();

        foreach ($folders as $folder) {
            
            if (in_array($folder->status, ['pending', 'new', 'draft'])) {
                $this->createTaskIfMissing($folder->id, "new_folder_{$folder->id}", "Nouveau dossier à analyser et deviser.", 'heroicon-m-document-plus', 'text-primary-500');
            }

            $lastMsg = FolderMessage::with('user')->where('folder_id', $folder->id)->latest()->first();
            if ($lastMsg && $lastMsg->user && $lastMsg->user->role === 'agency') {
                $this->createTaskIfMissing($folder->id, "unread_msg_{$lastMsg->id}", "Nouveau message de l'agence en attente.", 'heroicon-m-chat-bubble-left-ellipsis', 'text-warning-500');
            }

            foreach ($folder->folderItems as $item) {
                $productName = $item->product ? $item->product->name : 'Prestation sur-mesure';
                
                $dateStr = $item->service_date ? ' du ' . Carbon::parse($item->service_date)->format('d/m/Y') : '';
                $prestaLabel = $productName . $dateStr;

                // 💡 AJOUT : Lecture du statut pour auto-nettoyage des tâches
                $itemStatusName = $item->itemStatus ? mb_strtolower(trim($item->itemStatus->name), 'UTF-8') : 'inconnu';
                $stopItemStatuses = ['confirmé', 'confirme', 'annulé', 'annule', 'pas de disponibilité', 'pas de disponibilite', 'indisponible', 'en cours de traitement'];

                // 1. Tâche "Traiter et valider la prestation"
                $manageTaskCode = "manage_item_{$item->id}";
                if ($folder->status !== 'completed' && !in_array($itemStatusName, $stopItemStatuses)) {
                    $this->createTaskIfMissing(
                        $folder->id, 
                        $manageTaskCode, 
                        "Traiter et valider la prestation : {$prestaLabel}", 
                        'heroicon-m-sparkles', 
                        'text-primary-500'
                    );
                } else {
                    // Si on est en cours de traitement ou + : Auto-clôture
                    FolderTask::where('action_code', $manageTaskCode)
                        ->where('is_completed', false)
                        ->update(['is_completed' => true, 'completed_at' => now(), 'completed_by' => Auth::id() ?? 1]);
                }

                // 2. Alerte Spécifique (Problème / Action requise = ID 5 par exemple)
                $alertTaskCode = "item_alert_{$item->id}";
                if ($item->item_status_id == 5) {
                    $this->createTaskIfMissing($folder->id, $alertTaskCode, "Problème/Action requise sur : {$prestaLabel}.", 'heroicon-m-exclamation-triangle', 'text-danger-500');
                    // On rouvre si c'était fermé
                    FolderTask::where('action_code', $alertTaskCode)->where('is_completed', true)->update(['is_completed' => false, 'completed_at' => null, 'completed_by' => null]);
                } else {
                    // Si on change de statut, on supprime l'alerte
                    FolderTask::where('action_code', $alertTaskCode)
                        ->where('is_completed', false)
                        ->update(['is_completed' => true, 'completed_at' => now(), 'completed_by' => Auth::id() ?? 1]);
                }

                // 3. Alerte Prix d'achat manquant
                $priceTaskCode = "missing_purchase_price_{$item->id}";
                if (empty($item->purchase_total_price) || $item->purchase_total_price == 0) {
                    $this->createTaskIfMissing(
                        $folder->id, 
                        $priceTaskCode, 
                        "Saisir le prix d'achat pour : {$prestaLabel}", 
                        'heroicon-m-banknotes', 
                        'text-warning-600'
                    );

                    FolderTask::where('action_code', $priceTaskCode)
                        ->where('is_completed', true)
                        ->update([
                            'is_completed' => false,
                            'completed_at' => null,
                            'completed_by' => null,
                        ]);
                } else {
                    FolderTask::where('action_code', $priceTaskCode)
                        ->where('is_completed', false)
                        ->update([
                            'is_completed' => true,
                            'completed_at' => now(),
                            'completed_by' => Auth::id() ?? 1,
                        ]);
                }

                // 4. Alerte Facture Manquante
                $invoiceTaskCode = "invoice_missing_{$item->id}";
                $targetSupplier = $item->getTargetSupplier();
                
                if (in_array($folder->status, ['confirmed', 'completed']) && empty($item->invoice_received_at) && $targetSupplier && $targetSupplier->requires_invoice) {
                    $this->createTaskIfMissing(
                        $folder->id, 
                        $invoiceTaskCode, 
                        "Facture en attente (🏢 {$targetSupplier->name}) : {$prestaLabel}", 
                        'heroicon-m-document-currency-yen', 
                        'text-danger-600'
                    );

                    FolderTask::where('action_code', $invoiceTaskCode)
                        ->where('is_completed', true)
                        ->update([
                            'is_completed' => false,
                            'completed_at' => null,
                            'completed_by' => null,
                        ]);

                } else {
                    FolderTask::where('action_code', $invoiceTaskCode)
                        ->where('is_completed', false)
                        ->update([
                            'is_completed' => true,
                            'completed_at' => now(),
                            'completed_by' => Auth::id() ?? 1,
                        ]);
                }

                // 5. Ouverture des réservations (J- délai)
                $bookingTaskCode = "booking_open_{$item->id}";
                if ($item->product) {
                    $delay = $item->product->days_before_opening ?? null;
                    if ($delay !== null && $item->service_date && !in_array($itemStatusName, $stopItemStatuses) && $folder->status !== 'completed') {
                        $openDate = Carbon::parse($item->service_date)->subDays($delay);
                        if (now()->greaterThanOrEqualTo($openDate)) {
                            $this->createTaskIfMissing($folder->id, $bookingTaskCode, "Ouverture des réservations pour : {$prestaLabel}.", 'heroicon-m-calendar-days', 'text-success-500');
                        }
                    } else {
                        // Si le statut passe en cours de traitement/confirmé/annulé, on clôture la tâche d'ouverture
                        FolderTask::where('action_code', $bookingTaskCode)
                            ->where('is_completed', false)
                            ->update(['is_completed' => true, 'completed_at' => now(), 'completed_by' => Auth::id() ?? 1]);
                    }
                }
            }
        }
    }

    private function createTaskIfMissing($folderId, $code, $desc, $icon, $color)
    {
        $task = FolderTask::firstOrCreate(
            ['action_code' => $code],
            [
                'folder_id' => $folderId,
                'description' => $desc,
                'icon' => $icon,
                'color' => $color,
                'is_completed' => false,
            ]
        );

        if ($task->description !== $desc) {
            $task->update(['description' => $desc]);
        }
    }

    #[Computed]
    public function pendingTasks()
    {
        $this->generateMissingTasks();

        return FolderTask::where('is_completed', false)
            ->with(['folder.agency'])
            ->get()
            ->groupBy('folder_id')
            ->sortBy(function ($tasks) {
                return $tasks->first()->folder->updated_at;
            });
    }

    public function validateTaskAction(): Action
    {
        return Action::make('validateTask')
            ->requiresConfirmation()
            ->modalHeading('Tâche effectuée ?')
            ->modalDescription('Confirmez-vous avoir bien effectué cette action ? Elle sera archivée dans l\'historique du dossier.')
            ->modalSubmitActionLabel('Oui, valider')
            ->modalCancelActionLabel('Annuler')
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->action(function (array $arguments) {
                $this->markAsDone($arguments['task_id']);
            });
    }

    public function markAsDone($taskId)
    {
        $task = FolderTask::find($taskId);
        
        if ($task && !$task->is_completed) {
            
            $task->update([
                'is_completed' => true,
                'completed_by' => Auth::id(),
                'completed_at' => now(),
            ]);

            if (preg_match('/^booking_open_(\d+)$/', $task->action_code, $matches)) {
                $item = FolderItem::find($matches[1]);
                if ($item) {
                    try {
                        $item->deleteGoogleCalendarEvent();
                    } catch (\Exception $e) {
                        Log::error("CALENDAR Erreur suppression depuis widget : " . $e->getMessage());
                    }
                }
            }

            FolderHistory::create([
                'folder_id' => $task->folder_id,
                'user_id' => Auth::id(),
                'action' => "Tâche traitée : " . $task->description,
                'changes_payload' => json_encode(['task' => $task->description], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }
}