<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarif connu d'un billet de train / bus (prix adulte complet, par personne, en yens) :
 * trajet × train (facultatif) × classe, avec un prix par saison JR ou un prix unique.
 */
class TransportFare extends Model
{
    public const MODE_SEASONAL = 'seasonal';
    public const MODE_FLAT = 'flat';

    protected $fillable = [
        'departure_station', 'arrival_station', 'both_directions', 'train_name', 'product_option_id',
        'pricing_mode', 'price_low', 'price_normal', 'price_high', 'price_peak', 'price_flat', 'child_price',
        'valid_from', 'valid_to', 'is_active', 'source', 'notes',
    ];

    protected $casts = [
        'both_directions' => 'boolean',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'price_low' => 'integer',
        'price_normal' => 'integer',
        'price_high' => 'integer',
        'price_peak' => 'integer',
        'price_flat' => 'integer',
        'child_price' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (TransportFare $fare) {
            $fare->departure_key = self::stationKey($fare->departure_station);
            $fare->arrival_key = self::stationKey($fare->arrival_station);
            $fare->train_name = filled($fare->train_name) ? trim($fare->train_name) : null;
        });
    }

    public function productOption(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class);
    }

    /**
     * Forme normalisée d'un nom de gare : « Shin-Osaka Station (Gare Shinkansen) » → « shinosaka ».
     * Sert à retrouver un trajet même si le nom saisi diffère légèrement (casse, accents, « Station »).
     */
    public static function stationKey(?string $name): string
    {
        $s = preg_replace('/\(.*?\)/u', '', (string) $name);
        $s = \Illuminate\Support\Str::ascii($s);
        $s = mb_strtolower($s);
        $s = preg_replace('/\b(station|sta|eki)\b\.?/', '', $s);
        $s = preg_replace('/[^a-z0-9\p{Han}\p{Hiragana}\p{Katakana}]/u', '', $s);

        return mb_substr((string) $s, 0, 150);
    }

    /** Prix adulte pour une saison (low|normal|high|peak), ou null si la case est vide. */
    public function priceForSeason(string $season): ?int
    {
        if ($this->pricing_mode === self::MODE_FLAT) {
            return $this->price_flat;
        }

        return $this->{'price_' . $season} ?? null;
    }

    public function childPriceFor(int $adultPrice): int
    {
        if ($this->child_price) {
            return $this->child_price;
        }

        // Règle JR : moitié du tarif adulte, arrondie aux 10 ¥ inférieurs.
        return (int) (floor($adultPrice / 2 / 10) * 10);
    }
}
