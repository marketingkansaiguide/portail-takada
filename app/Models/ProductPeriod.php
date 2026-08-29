<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ProductPeriod extends Model
{
    protected $fillable = ['product_id', 'name', 'start_date', 'end_date', 'date_ranges'];

    protected $casts = [
        'date_ranges' => 'array',
    ];

    protected static function booted(): void
    {
        // Les deux champs historiques restent renseignés avec la première plage
        // afin de rester compatibles avec les données et intégrations existantes.
        static::saving(function (self $period): void {
            $range = $period->dateRanges()[0] ?? null;

            if ($range) {
                $period->start_date = $range['start_date'];
                $period->end_date = $range['end_date'];
            }
        });
    }

    /** @return array<int, array{start_date: string, end_date: string}> */
    public function dateRanges(): array
    {
        $ranges = collect($this->date_ranges ?? [])
            ->map(fn ($range) => [
                'start_date' => trim((string) data_get($range, 'start_date')),
                'end_date' => trim((string) data_get($range, 'end_date')),
            ])
            ->filter(fn (array $range): bool => $range['start_date'] !== '' && $range['end_date'] !== '')
            ->values()
            ->all();

        if ($ranges === [] && filled($this->start_date) && filled($this->end_date)) {
            $ranges[] = [
                'start_date' => (string) $this->start_date,
                'end_date' => (string) $this->end_date,
            ];
        }

        return $ranges;
    }

    public function matchesDate(string|Carbon|null $date): bool
    {
        if (blank($date)) {
            return false;
        }

        $monthDay = $date instanceof Carbon ? $date->format('m-d') : Carbon::parse($date)->format('m-d');
        $ranges = $this->dateRanges();

        // Une ancienne saison sans bornes reste valable toute l'année.
        if ($ranges === []) {
            return true;
        }

        foreach ($ranges as $range) {
            if ($range['start_date'] <= $range['end_date']) {
                if ($monthDay >= $range['start_date'] && $monthDay <= $range['end_date']) {
                    return true;
                }
            } elseif ($monthDay >= $range['start_date'] || $monthDay <= $range['end_date']) {
                return true;
            }
        }

        return false;
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function productPrices() {
        return $this->hasMany(ProductPrice::class);
    }
}
