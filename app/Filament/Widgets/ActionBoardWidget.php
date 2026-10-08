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

    #[Computed]
    public function pendingTasks()
    {
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
