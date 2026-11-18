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

    /** Calendriers où l'on saisit des périodes (colonne « company » = code). « K » (Kyushu) suit A sans saison basse. */
    public const CALENDARS = [
        'A' => 'A — Tokaido, Sanyo, Kyushu…',
        'B' => 'B — JR East…',
    ];

    protected $fillable = ['company', 'season', 'starts_on', 'ends_on', 'notes'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    protected static function booted(): void
    {
        $flush = fn () => app(\App\Support\TransportFareResolver::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * Saison applicable à une date pour un calendrier : « A » ou « B » (périodes de ce calendrier),
     * « K » (périodes du calendrier A sans les périodes basses), null = pas de saison (saison normale).
     * Une période sans calendrier (anciennes données) vaut pour tous les calendriers.
     * En cas de chevauchement, la plus chère l'emporte (peak > high > low).
     */
    public static function seasonFor(\DateTimeInterface|string $date, ?string $calendar = null): string
    {
        if ($calendar === null) {
            return 'normal';
        }

        return static::resolveSeason(
            static::query()->get(['company', 'season', 'starts_on', 'ends_on']),
            $date,
            $calendar,
        );
    }

    /**
     * Même règle que seasonFor(), appliquée à des périodes déjà chargées (une seule requête pour
     * plusieurs dates ou calendriers) : le filtrage se fait en PHP.
     *
     * @param  iterable<int, static>  $periods
     */
    public static function resolveSeason(iterable $periods, \DateTimeInterface|string $date, ?string $calendar): string
    {
        if ($calendar === null) {
            return 'normal';
        }

        $day = \Illuminate\Support\Carbon::parse($date)->toDateString();
        $source = $calendar === 'K' ? 'A' : $calendar;
        $found = [];
        foreach ($periods as $p) {
            if ($p->starts_on?->toDateString() <= $day && $p->ends_on?->toDateString() >= $day
                && ($p->company === null || $p->company === $source)) {
                $found[$p->season] = true;
            }
        }

        foreach (['peak', 'high', 'low'] as $season) {
            if ($calendar === 'K' && $season === 'low') {
                continue;
            }
            if (isset($found[$season])) {
                return $season;
            }
        }

        return 'normal';
    }
}
