<?php

namespace App\Jobs;

use App\Models\FolderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Synchronisation Google Calendar d'une prestation, après validation en base.
 * Idempotente (google_calendar_event_id : mise à jour au lieu de recréation),
 * suivie (calendar_sync_*) et relancée en cas d'échec (calendar:retry-sync).
 */
class SyncFolderItemCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $folderItemId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('calendar-item-' . $this->folderItemId))->releaseAfter(30)];
    }

    public function handle(): void
    {
        $item = FolderItem::with(['folder', 'product', 'itemStatus'])->find($this->folderItemId);
        if (! $item) {
            return;
        }

        $error = app(\App\Services\FolderItemCalendar::class)->sync($item);

        if ($error !== null) {
            DB::table('folder_items')->where('id', $item->id)->update([
                'calendar_sync_status' => 'failed',
                'calendar_sync_error' => $error,
            ]);

            throw new \RuntimeException('Google Calendar : ' . $error);
        }

        DB::table('folder_items')->where('id', $item->id)->update([
            'calendar_sync_status' => 'synced',
            'calendar_sync_error' => null,
            'calendar_synced_at' => now(),
        ]);
    }
}
