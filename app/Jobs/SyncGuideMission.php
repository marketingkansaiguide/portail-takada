<?php

namespace App\Jobs;

use App\Models\FolderItem;
use App\Services\GuideMissionPayloadFactory;
use App\Services\GuidePlanningApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGuideMission implements ShouldQueue
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

    public function handle(GuideMissionPayloadFactory $factory, GuidePlanningApi $api): void
    {
        $item = FolderItem::with(['folder.agency', 'product', 'productOption'])->find($this->folderItemId);
        if (! $item || ! in_array($item->folder?->status, ['confirmed', 'completed'], true) || (int) $item->product?->category_id !== 4) {
            return;
        }

        $item->update(['guide_sync_status' => 'pending', 'guide_sync_error' => null]);

        try {
            $payload = $factory->make($item);
            $mission = $api->syncMission($payload);
            $item->update([
                'guide_mission_id' => (int) $mission['id'],
                'guide_sync_status' => 'synced',
                'guide_sync_error' => null,
                'guide_synced_at' => now(),
            ]);
            Log::channel('guide_integration')->info('Mission de guide synchronisée.', $this->context($item, ['mission_id' => (int) $mission['id']]));
        } catch (Throwable $exception) {
            $item->update(['guide_sync_status' => 'failed', 'guide_sync_error' => mb_substr($exception->getMessage(), 0, 2000)]);
            Log::channel('guide_integration')->error('Échec de synchronisation de mission.', $this->context($item, ['error' => $exception->getMessage()]));
            throw $exception;
        }
    }

    /** @param array<string, mixed> $extra @return array<string, mixed> */
    private function context(FolderItem $item, array $extra = []): array
    {
        return array_merge([
            'folder_id' => $item->folder_id,
            'folder_reference' => $item->folder?->reference,
            'folder_item_id' => $item->id,
            'agency' => $item->folder?->agency?->name,
            'product' => $item->product?->name,
            'service_date' => $item->service_date?->format('Y-m-d'),
        ], $extra);
    }
}
