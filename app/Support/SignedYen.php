<?php

namespace App\Support;

final class SignedYen
{
    /** « + 1 200 » ou « − 4 000 » (vrai signe moins), sans l'unité. */
    public static function fmt(float|int $value): string
    {
        return ($value < 0 ? '−' : '+') . ' ' . number_format(abs($value), 0, '.', ' ');
    }
}
