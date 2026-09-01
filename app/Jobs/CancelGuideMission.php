<?php

namespace App\Jobs;

use App\Models\FolderItem;
use App\Services\GuidePlanningApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CancelGuideMission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $folderItemId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping("guide-mission-{$this->folderItemId}"))->expireAfter(120)];
    }

    public function handle(GuidePlanningApi $api): void
    {
        $item = FolderItem::with(['folder', 'itemStatus'])->find($this->folderItemId);
        if (! $item || ! $item->guide_mission_id || ! $this->mustBeCancelled($item)) {
            return;
        }

        $item->update(['guide_sync_status' => 'cancelling', 'guide_sync_error' => null]);

        try {
            $api->cancelMission((int) $item->guide_mission_id, (int) $item->id);
            $item->update([
                'guide_sync_status' => 'cancelled',
                'guide_sync_error' => null,
            ]);
            Log::channel('guide_integration')->info('Mission de guide annulée.', [
                'folder_id' => $item->folder_id,
                'folder_item_id' => $item->id,
                'mission_id' => (int) $item->guide_mission_id,
            ]);
        } catch (Throwable $exception) {
            $item->update(['guide_sync_status' => 'failed', 'guide_sync_error' => mb_substr($exception->getMessage(), 0, 2000)]);
            Log::channel('guide_integration')->error('Échec d’annulation de mission de guide.', [
                'folder_id' => $item->folder_id,
                'folder_item_id' => $item->id,
                'mission_id' => (int) $item->guide_mission_id,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    private function mustBeCancelled(FolderItem $item): bool
    {
        if ($item->folder?->status === 'cancelled') {
            return true;
        }

        return str_contains(Str::lower(Str::ascii((string) $item->itemStatus?->name)), 'annul');
    }
}
