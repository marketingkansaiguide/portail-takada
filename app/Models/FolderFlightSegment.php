<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Segment de vol saisi pour information (aucune réservation aérienne). Heures locales + fuseau. */
class FolderFlightSegment extends Model
{
    public const DIRECTIONS = ['outbound' => 'Aller', 'return' => 'Retour', 'other' => 'Autre / correspondance'];

    /** Fuseaux proposés par défaut selon le code aéroport (modifiables). */
    public const AIRPORT_TZ = [
        'NRT' => 'Asia/Tokyo', 'HND' => 'Asia/Tokyo', 'KIX' => 'Asia/Tokyo', 'ITM' => 'Asia/Tokyo', 'NGO' => 'Asia/Tokyo',
        'FUK' => 'Asia/Tokyo', 'CTS' => 'Asia/Tokyo', 'OKA' => 'Asia/Tokyo', 'CMB' => 'Asia/Colombo',
        'CDG' => 'Europe/Paris', 'ORY' => 'Europe/Paris', 'BRU' => 'Europe/Brussels', 'LHR' => 'Europe/London',
        'AMS' => 'Europe/Amsterdam', 'FRA' => 'Europe/Berlin', 'DOH' => 'Asia/Qatar', 'DXB' => 'Asia/Dubai',
        'SIN' => 'Asia/Singapore', 'BKK' => 'Asia/Bangkok', 'ICN' => 'Asia/Seoul', 'HKG' => 'Asia/Hong_Kong',
    ];

    protected $guarded = [];

    protected $casts = ['departure_local' => 'datetime', 'arrival_local' => 'datetime'];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public static function timezoneFor(?string $airport): ?string
    {
        return self::AIRPORT_TZ[strtoupper(trim((string) $airport))] ?? null;
    }

    /** Durée réelle en minutes, calculée à partir des fuseaux des deux aéroports. */
    public function durationMinutes(): ?int
    {
        if (! $this->departure_local || ! $this->arrival_local || ! $this->departure_tz || ! $this->arrival_tz) {
            return null;
        }
        $dep = \Carbon\Carbon::parse($this->departure_local->format('Y-m-d H:i:s'), $this->departure_tz);
        $arr = \Carbon\Carbon::parse($this->arrival_local->format('Y-m-d H:i:s'), $this->arrival_tz);

        return (int) $dep->diffInMinutes($arr, false);
    }
}
