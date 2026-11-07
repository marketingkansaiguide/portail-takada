<?php

namespace App\Support;

use App\Models\FolderStay;

final class FolderStayLabels
{
    public static function status(?string $status): string
    {
        return __(FolderStay::STATUSES[$status] ?? '—');
    }
}
