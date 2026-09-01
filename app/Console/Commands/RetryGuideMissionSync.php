<?php

namespace App\Console\Commands;

use App\Jobs\SyncGuideMission;
use App\Models\FolderItem;
use Illuminate\Console\Command;

class RetryGuideMissionSync extends Command
{
    protected $signature = 'guide-missions:retry {--item= : Identifiant précis de prestation}';
    protected $description = 'Remet en file les missions de guide en échec ou une prestation précise';

    public function handle(): int
    {
        $query = FolderItem::query()
            ->whereHas('product', fn ($builder) => $builder->where('category_id', 4))
            ->whereHas('folder', fn ($builder) => $builder->whereIn('status', ['confirmed', 'completed']));

        if ($this->option('item')) {
            $query->whereKey((int) $this->option('item'));
        } else {
            $query->where('guide_sync_status', 'failed');
        }

        $count = 0;
        $query->eachById(function (FolderItem $item) use (&$count): void {
            $item->update(['guide_sync_status' => 'pending', 'guide_sync_error' => null]);
            SyncGuideMission::dispatch($item->id);
            $count++;
        });

        $this->info("{$count} synchronisation(s) remise(s) en file.");

        return self::SUCCESS;
    }
}
