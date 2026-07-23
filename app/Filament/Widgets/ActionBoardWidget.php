<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

use App\Models\Folder;
use App\Models\FolderMessage;
use App\Models\FolderTask;
use App\Models\FolderHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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
        // 1. On récupère les dossiers actifs
        $folders = Folder::with(['folderItems.product', 'agency'])
            ->whereNotIn('status', ['cancelled'])
            ->get();

        foreach ($folders as $folder) {
            
            // 2. Tâche générique de nouveau dossier
            if (in_array($folder->status, ['pending', 'new', 'draft'])) {
                $this->createTaskIfMissing($folder->id, "new_folder_{$folder->id}", "Nouveau dossier à analyser et deviser.", 'heroicon-m-document-plus', 'text-primary-500');
            }

            // 3. Détection des nouveaux messages de l'Agence
            $lastMsg = FolderMessage::with('user')->where('folder_id', $folder->id)->latest()->first();
            if ($lastMsg && $lastMsg->user && $lastMsg->user->role === 'agency') {
                $this->createTaskIfMissing($folder->id, "unread_msg_{$lastMsg->id}", "Nouveau message de l'agence en attente.", 'heroicon-m-chat-bubble-left-ellipsis', 'text-warning-500');
            }

            // 4. Analyse des prestations (FolderItems)
            foreach ($folder->folderItems as $item) {
                $productName = $item->product ? $item->product->name : 'Prestation sur-mesure';

                // Règle universelle : Dès qu'une prestation existe, on crée une tâche
                if ($folder->status !== 'completed') {
                    $this->createTaskIfMissing(
                        $folder->id, 
                        "manage_item_{$item->id}", 
                        "Traiter et valider la prestation : {$productName}", 
                        'heroicon-m-sparkles', 
                        'text-primary-500'
                    );
                }

                // Alertes spécifiques (ex: ID 5 = Action requise / Refus)
                if ($item->item_status_id == 5) {
                    $this->createTaskIfMissing($folder->id, "item_alert_{$item->id}", "Problème/Action requise sur : {$productName}.", 'heroicon-m-exclamation-triangle', 'text-danger-500');
                }

                // 💡 ALERTE FACTURE MANQUANTE (avec requires_invoice)
                $invoiceTaskCode = "invoice_missing_{$item->id}";
                $targetSupplier = $item->getTargetSupplier();
                
                // On vérifie que la case requires_invoice est bien cochée chez le fournisseur
                if (in_array($folder->status, ['confirmed', 'completed']) && empty($item->invoice_received_at) && $targetSupplier && $targetSupplier->requires_invoice) {
                    $this->createTaskIfMissing(
                        $folder->id, 
                        $invoiceTaskCode, 
                        "Facture en attente (🏢 {$targetSupplier->name}) : {$productName}", 
                        'heroicon-m-document-currency-yen', 
                        'text-danger-600'
                    );

                    // 💡 CORRECTION DU BUG : On force la réouverture si la tâche avait été clôturée
                    FolderTask::where('action_code', $invoiceTaskCode)
                        ->where('is_completed', true)
                        ->update([
                            'is_completed' => false,
                            'completed_at' => null,
                            'completed_by' => null,
                        ]);

                } else {
                    // Si on a reçu la facture ou que la condition n'est plus remplie, on clôture la tâche
                    FolderTask::where('action_code', $invoiceTaskCode)
                        ->where('is_completed', false)
                        ->update([
                            'is_completed' => true,
                            'completed_at' => now(),
                            'completed_by' => Auth::id() ?? 1,
                        ]);
                }

                // Ouverture des ventes (J- délai)
                if ($item->product) {
                    $delay = $item->product->booking_open_delay ?? null;
                    if ($delay !== null && $item->service_date) {
                        $openDate = Carbon::parse($item->service_date)->subDays($delay);
                        if (now()->greaterThanOrEqualTo($openDate) && $folder->status !== 'completed') {
                            $this->createTaskIfMissing($folder->id, "booking_open_{$item->id}", "Ouverture des réservations pour : {$productName}.", 'heroicon-m-calendar-days', 'text-success-500');
                        }
                    }
                }
            }
        }
    }

    private function createTaskIfMissing($folderId, $code, $desc, $icon, $color)
    {
        FolderTask::firstOrCreate(
            ['action_code' => $code],
            [
                'folder_id' => $folderId,
                'description' => $desc,
                'icon' => $icon,
                'color' => $color,
                'is_completed' => false,
            ]
        );
    }

    #[Computed]
    public function pendingTasks()
    {
        // Génération en temps réel
        $this->generateMissingTasks();

        // Récupération, groupement par dossier et tri
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
            
            // 1. On ferme la tâche
            $task->update([
                'is_completed' => true,
                'completed_by' => Auth::id(),
                'completed_at' => now(),
            ]);

            // 2. On écrit dans l'historique du dossier
            FolderHistory::create([
                'folder_id' => $task->folder_id,
                'user_id' => Auth::id(),
                'action' => "Tâche traitée : " . $task->description,
                'changes_payload' => json_encode(['task' => $task->description], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }
}