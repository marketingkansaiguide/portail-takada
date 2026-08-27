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
use App\Models\Setting;
use App\Mail\IcCardOpeningAlertMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;

class ActionBoardWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.widgets.action-board-widget';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 4;

    public function mount()
    {
        // Géré dynamiquement par #[Computed]
    }

    private function generateMissingTasks(): void
    {
        $activeTasks = FolderTask::where('is_completed', false)->get();
        foreach ($activeTasks as $task) {
            if (preg_match('/^(manage_item|item_alert|invoice_missing|booking_open|missing_purchase_price|ic_cards_opening)_(\d+)$/', $task->action_code, $matches)) {
                $itemId = $matches[2];
                if (!FolderItem::find($itemId)) {
                    $task->delete();
                }
            }

            if (preg_match('/^(unread_msg|message_action_required)_(\d+)$/', $task->action_code, $matches)) {
                $message = FolderMessage::find($matches[2]);
                if (!$message || ($matches[1] === 'message_action_required' && !$message->is_action_required)) {
                    $task->delete();
                }
            }
        }

        $folders = Folder::with(['allFolderItems.product', 'allFolderItems.itemStatus', 'agency'])
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $newIcAlertItems = [];

        foreach ($folders as $folder) {
            
            $newFolderTaskCode = "new_folder_{$folder->id}";
            if (in_array($folder->status, ['pending', 'new', 'draft'], true)) {
                $this->createTaskIfMissing($folder->id, $newFolderTaskCode, __('Nouveau dossier à analyser et deviser.'), 'heroicon-m-document-plus', 'text-primary-500');
            } else {
                $this->completeTaskByCode($newFolderTaskCode);
            }

            $latestAgencyMessage = FolderMessage::query()
                ->with('user')
                ->where('folder_id', $folder->id)
                ->whereHas('user', fn ($query) => $query->where('role', 'agency'))
                ->latest()
                ->first();
            $unreadMessageTaskCode = $latestAgencyMessage ? "unread_msg_{$latestAgencyMessage->id}" : null;

            FolderTask::where('folder_id', $folder->id)
                ->where('action_code', 'like', 'unread_msg_%')
                ->when($unreadMessageTaskCode, fn ($query) => $query->where('action_code', '!=', $unreadMessageTaskCode))
                ->where('is_completed', false)
                ->update(['is_completed' => true, 'completed_at' => now(), 'completed_by' => Auth::id()]);

            if ($latestAgencyMessage) {
                $this->createTaskIfMissing(
                    $folder->id,
                    $unreadMessageTaskCode,
                    __('Nouveau message de l’agence en attente.'),
                    'heroicon-m-chat-bubble-left-ellipsis',
                    'text-warning-500',
                );
            }

            $actionRequiredMessages = FolderMessage::where('folder_id', $folder->id)
                ->where('is_action_required', true)
                ->get();
            $actionRequiredCodes = $actionRequiredMessages
                ->map(fn (FolderMessage $message): string => "message_action_required_{$message->id}")
                ->all();

            FolderTask::where('folder_id', $folder->id)
                ->where('action_code', 'like', 'message_action_required_%')
                ->when($actionRequiredCodes !== [], fn ($query) => $query->whereNotIn('action_code', $actionRequiredCodes))
                ->where('is_completed', false)
                ->update(['is_completed' => true, 'completed_at' => now(), 'completed_by' => Auth::id()]);

            foreach ($actionRequiredMessages as $message) {
                $excerpt = filled($message->message) ? str($message->message)->squish()->limit(110) : __('Pièce jointe ou demande sans texte');
                $this->createTaskIfMissing(
                    $folder->id,
                    "message_action_required_{$message->id}",
                    __('Répondre au message nécessitant une action : :message', ['message' => $excerpt]),
                    'heroicon-m-chat-bubble-left-right',
                    'text-danger-500',
                );
            }

            foreach ($folder->allFolderItems as $item) {
                $productName = $item->product ? $item->product->name : 'Prestation sur-mesure';
                
                $dateStr = $item->service_date ? ' du ' . Carbon::parse($item->service_date)->format('d/m/Y') : '';
                $prestaLabel = $productName . $dateStr;

                $itemStatusName = $item->itemStatus ? mb_strtolower(trim($item->itemStatus->name), 'UTF-8') : 'inconnu';
                $stopItemStatuses = ['confirmé', 'confirme', 'annulé', 'annule', 'pas de disponibilité', 'pas de disponibilite', 'indisponible', 'en cours de traitement'];

                // 🚨 ALERTE SPÉCIFIQUE : OUVERTURE VENTES CARTES IC (product_id = 3)
                $icTaskCode = "ic_cards_opening_{$item->id}";
                if ($item->product_id == 3 && $itemStatusName === 'en attente de validation' && !in_array($folder->status, ['draft', 'completed', 'cancelled'], true)) {
                    $effectiveDate = $item->service_date ?? $folder->start_date;
                    $delay = $item->product?->days_before_opening ?? 0;

                    if ($effectiveDate) {
                        $openingDate = Carbon::parse($effectiveDate)->subDays($delay)->startOfDay();

                        if (now()->startOfDay()->greaterThanOrEqualTo($openingDate)) {
                            $qty = $item->quantity ?? 1;
                            $paxName = $folder->lead_traveler_name ?? 'Client';
                            
                            $isNew = $this->createTaskIfMissing(
                                $folder->id,
                                $icTaskCode,
            __('🚨 Cartes IC à commander : Ouverture des ventes atteinte pour le dossier :reference (:name - :qty carte(s)).', ['reference' => $folder->reference, 'name' => $paxName, 'qty' => $qty]),
                                'heroicon-m-credit-card',
                                'text-danger-500'
                            );

                            if ($isNew) {
                                $newIcAlertItems[] = $item;
                            }
                        } else {
                            $this->completeTaskByCode($icTaskCode);
                        }
                    } else {
                        $this->completeTaskByCode($icTaskCode);
                    }
                } else {
                    $this->completeTaskByCode($icTaskCode);
                }

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
                    FolderTask::where('action_code', $manageTaskCode)
                        ->where('is_completed', false)
                        ->update(['is_completed' => true, 'completed_at' => now(), 'completed_by' => Auth::id() ?? 1]);
                }

                $alertTaskCode = "item_alert_{$item->id}";
                if ($item->item_status_id == 5) {
            $this->createTaskIfMissing($folder->id, $alertTaskCode, __('Problème/Action requise sur : :service.', ['service' => $prestaLabel]), 'heroicon-m-exclamation-triangle', 'text-danger-500');
                    FolderTask::where('action_code', $alertTaskCode)->where('is_completed', true)->update(['is_completed' => false, 'completed_at' => null, 'completed_by' => null]);
                } else {
                    FolderTask::where('action_code', $alertTaskCode)
                        ->where('is_completed', false)
                        ->update(['is_completed' => true, 'completed_at' => now(), 'completed_by' => Auth::id() ?? 1]);
                }

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

                $bookingTaskCode = "booking_open_{$item->id}";
                if ($item->product) {
                    $delay = $item->product->days_before_opening ?? null;
                    if ($delay !== null && $item->service_date && !in_array($itemStatusName, $stopItemStatuses) && $folder->status !== 'completed') {
                        $openDate = Carbon::parse($item->service_date)->subDays($delay);
                        if (now()->greaterThanOrEqualTo($openDate)) {
                            $this->createTaskIfMissing($folder->id, $bookingTaskCode, __('Ouverture des réservations pour : :service.', ['service' => $prestaLabel]), 'heroicon-m-calendar-days', 'text-success-500');
                        } else {
                            $this->completeTaskByCode($bookingTaskCode);
                        }
                    } else {
                        $this->completeTaskByCode($bookingTaskCode);
                    }
                } else {
                    $this->completeTaskByCode($bookingTaskCode);
                }
            }
        }

        // Envoi du mail récapitulatif unique s'il y a de nouvelles alertes IC
        if (count($newIcAlertItems) > 0) {
            try {
                $setting = Setting::first();
                $adminEmails = [];

                if ($setting && !empty($setting->admin_email_notifications)) {
                    $adminEmails = explode(',', $setting->admin_email_notifications);
                    $adminEmails = array_map('trim', $adminEmails);
                    $adminEmails = array_filter($adminEmails);
                }

                if (empty($adminEmails)) {
                    $adminEmails = [env('MAIL_ADMIN_RECEIVER', env('MAIL_FROM_ADDRESS'))];
                }

                if (!empty($adminEmails)) {
                    Mail::to($adminEmails)->send(new IcCardOpeningAlertMail($newIcAlertItems));
                }
            } catch (\Exception $e) {
                Log::error("Erreur e-mail alerte Carte IC depuis Widget : " . $e->getMessage());
            }
        }
    }

    private function createTaskIfMissing(int $folderId, string $code, string $desc, string $icon, string $color): bool
    {
        $task = FolderTask::where('action_code', $code)->first();

        if (!$task) {
            FolderTask::create([
                'folder_id' => $folderId,
                'action_code' => $code,
                'description' => $desc,
                'icon' => $icon,
                'color' => $color,
                'is_completed' => false,
            ]);
            return true; // Nouvelle tâche
        }

        if ($task->description !== $desc || $task->icon !== $icon || $task->color !== $color) {
            $task->update([
                'description' => $desc,
                'icon' => $icon,
                'color' => $color,
            ]);
        }

        return false;
    }

    private function completeTaskByCode(string $code): void
    {
        FolderTask::where('action_code', $code)
            ->where('is_completed', false)
            ->update([
                'is_completed' => true,
                'completed_at' => now(),
                'completed_by' => Auth::id(),
            ]);
    }

    #[Computed]
    public function pendingTasks()
    {
        $this->generateMissingTasks();

        $tasks = FolderTask::where('is_completed', false)
            ->with(['folder.agency'])
            ->get()
            ->filter(fn (FolderTask $task): bool => $task->folder !== null);

        // The generic "manage service" reminder adds no value while a more precise
        // task for the same service is pending. It returns automatically once the
        // specific blocker is resolved, so no work item is lost.
        $itemsWithSpecificTasks = $tasks
            ->filter(fn (FolderTask $task): bool => preg_match('/^(item_alert|invoice_missing|booking_open|missing_purchase_price|ic_cards_opening)_\d+$/', $task->action_code) === 1)
            ->map(function (FolderTask $task): ?int {
                return preg_match('/_(\d+)$/', $task->action_code, $matches) ? (int) $matches[1] : null;
            })
            ->filter()
            ->unique()
            ->all();

        return $tasks
            ->reject(function (FolderTask $task) use ($itemsWithSpecificTasks): bool {
                return preg_match('/^manage_item_(\d+)$/', $task->action_code, $matches)
                    && in_array((int) $matches[1], $itemsWithSpecificTasks, true);
            })
            ->sortBy([
                ['priority_rank', 'asc'],
                ['created_at', 'asc'],
            ])
            ->groupBy('folder_id')
            ->sortBy(function ($tasks): array {
                return [
                    $tasks->min('priority_rank'),
                    $tasks->first()->folder->start_date?->timestamp ?? PHP_INT_MAX,
                    $tasks->first()->folder->updated_at?->timestamp ?? PHP_INT_MAX,
                ];
            });
    }

    #[Computed]
    public function taskSummary(): array
    {
        $tasks = $this->pendingTasks->flatten();

        return [
            'total' => $tasks->count(),
            'critical' => $tasks->where('priority', 'critical')->count(),
            'attention' => $tasks->where('priority', 'attention')->count(),
            'normal' => $tasks->where('priority', 'normal')->count(),
        ];
    }

    public function validateTaskAction(): Action
    {
        return Action::make('validateTask')
            ->requiresConfirmation()
             ->modalHeading(__('Tâche effectuée ?'))
             ->modalDescription(__('Confirmez-vous avoir bien effectué cette action ? Elle sera archivée dans l\'historique du dossier.'))
            ->modalSubmitActionLabel('Oui, valider')
            ->modalCancelActionLabel(__('Annuler'))
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

            FolderHistory::logConsolidated(
                $task->folder_id,
                __('Tâche traitée'),
                __('Tâche traitée :') . ' ' . $task->description,
                'task',
                [],
                ['task_id' => $task->id, 'event' => 'completed'],
            );
        }
    }
}
