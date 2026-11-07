<?php

namespace App\Console\Commands;

use App\Jobs\SyncFolderItemCalendar;
use App\Models\FolderItem;
use Illuminate\Console\Command;

class RetryCalendarSync extends Command
{
    protected $signature = 'calendar:retry-sync {--item= : Identifiant d\'une prestation précise}';

    protected $description = 'Remet en file les synchronisations Google Calendar en échec (ou une prestation précise)';

    public function handle(): int
    {
        $query = FolderItem::query();
        $this->option('item') ? $query->whereKey((int) $this->option('item')) : $query->where('calendar_sync_status', 'failed');

        $count = 0;
        $query->eachById(function (FolderItem $item) use (&$count): void {
            $item->forceFill(['calendar_sync_status' => 'pending', 'calendar_sync_error' => null])->saveQuietly();
            SyncFolderItemCalendar::dispatch($item->id);
            $count++;
        });

        $this->info("{$count} synchronisation(s) remise(s) en file.");

        return self::SUCCESS;
    }
}
