<?php

namespace App\Services;

use App\Models\FolderItem;

/** Point d'appel Google Calendar d'une prestation (remplaçable en test). */
class FolderItemCalendar
{
    /** @return string|null message d'erreur, ou null si la synchronisation a réussi (ou n'était pas nécessaire) */
    public function sync(FolderItem $item): ?string
    {
        $item->syncGoogleCalendar();

        return $item->lastCalendarError;
    }
}
