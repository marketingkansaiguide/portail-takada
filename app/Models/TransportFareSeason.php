<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Période de saison tarifaire JR (basse, haute, très haute) ; hors périodes = saison normale. */
class TransportFareSeason extends Model
{
    public const SEASONS = [
        'low' => 'Basse',
        'normal' => 'Normale',
        'high' => 'Haute',
        'peak' => 'Très haute',
    ];

    protected $fillable = ['company', 'season', 'starts_on', 'ends_on', 'notes'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** Saison applicable à une date ; en cas de chevauchement, la plus chère l'emporte. */
    public static function seasonFor(\DateTimeInterface|string $date): string
    {
        $day = \Illuminate\Support\Carbon::parse($date)->toDateString();
        $found = static::query()
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            ->pluck('season')
            ->all();

        foreach (['peak', 'high', 'low'] as $season) {
            if (in_array($season, $found, true)) {
                return $season;
            }
        }

        return 'normal';
    }
}
