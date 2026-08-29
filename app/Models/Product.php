<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $guarded = [];

    protected $casts = [
        'images' => 'array',
        'child_age_limit' => 'integer',
        'teen_age_limit' => 'integer',
        'max_pax' => 'integer',
        'days_before_request_deadline' => 'integer',
        'available_days' => 'array',
        'blackout_dates' => 'array',
        'custom_field_definitions' => 'array',
        'is_lottery' => 'boolean',
        'is_on_demand' => 'boolean',
        'is_public' => 'boolean',
        'supplier_fax_header' => 'array',
        'transport_routes' => 'array',
        'enable_age_selector' => 'boolean',
        'enable_child_selector' => 'boolean',
        'enable_teen_selector' => 'boolean',
    ];

    public function isTransport(): bool
    {
        return $this->product_type === 'transport';
    }

    public function usesGlobalGuidingRate(?ProductPrice $price): bool
    {
        return (int) $this->category_id === 4 && $price?->isGlobalRate();
    }

    public function baseRateTotal(?ProductPrice $price, int $quantity, ?ProductPeriod $period = null, ?int $age = null): float
    {
        if (! $price) {
            return 0.0;
        }

        $baseTotal = $this->usesGlobalGuidingRate($price)
            ? (float) $price->price
            : (float) $price->price * max(1, $quantity);

        $legacyExtraPax = (int) $this->category_id === 4
            ? max(0, $quantity - (int) ($price->max_pax ?? $quantity))
            : 0;

        $extraTotal = $legacyExtraPax * (float) ($price->extra_pax_price ?? 0);

        if ((int) $this->category_id === 4 && $period) {
            foreach ($period->productPrices
                ->filter(fn (ProductPrice $candidate): bool => $candidate->isExtraPaxRate())
                ->filter(fn (ProductPrice $candidate): bool => $age === null || ((int) $candidate->min_age <= $age && (int) $candidate->max_age >= $age)) as $extraRate) {
                $from = max(1, (int) $extraRate->min_pax);
                $to = max($from, (int) ($extraRate->max_pax ?? $quantity));
                $affectedPax = max(0, min($quantity, $to) - $from + 1);
                $extraTotal += $affectedPax * (float) $extraRate->price;
            }
        }

        return $baseTotal + $extraTotal;
    }

    /**
     * Date limite à laquelle une agence peut encore demander la prestation.
     */
    public function getAgencyRequestDeadline(string | Carbon | null $serviceDate): ?Carbon
    {
        $daysBeforeDeadline = (int) ($this->days_before_request_deadline ?? 0);

        if ($daysBeforeDeadline < 1 || blank($serviceDate)) {
            return null;
        }

        return Carbon::parse($serviceDate)->startOfDay()->subDays($daysBeforeDeadline);
    }

    public function isAgencyRequestDeadlineExceeded(string | Carbon | null $serviceDate): bool
    {
        $deadline = $this->getAgencyRequestDeadline($serviceDate);

        return $deadline !== null && Carbon::today()->gt($deadline);
    }

    /**
     * URL de la première image disponible du produit, pour les listes.
     */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        $images = $this->images;

        if (is_string($images)) {
            $images = json_decode($images, true);
        }

        if (! is_array($images)) {
            return null;
        }

        $image = collect($images)
            ->filter(fn ($image) => filled($image))
            ->first();

        if (! is_string($image)) {
            return null;
        }

        $image = trim($image);

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        if (str_starts_with($image, '/storage/') || str_starts_with($image, 'storage/')) {
            return asset(ltrim($image, '/'));
        }

        return Storage::disk('public')->url($image);
    }

    /**
     * Retourne le tarif adulte et le tarif enfant de la grille sélectionnée.
     * Les bornes de pax et d'âge doivent toutes les deux correspondre.
     */
    public function calculateAgeBasedBreakdown($period, int $totalPax, int $adults, int $children, int $teenagers = 0): ?array
    {
        if (!$this->usesAgeSelector() || $totalPax < 1) {
            return null;
        }

        $prices = $period?->productPrices;
        if (!$prices || $prices->isEmpty()) {
            return null;
        }

        $childAge = 0;
        $teenAge = (int) ($this->child_age_limit ?? 11) + 1;
        $adultAge = $this->teen_age_limit !== null ? (int) $this->teen_age_limit + 1 : $teenAge;

        $findPrice = function (int $age, int $paxCount) use ($prices, $period): ?array {
            $regularPrices = $prices->reject(fn (ProductPrice $price): bool => $price->isExtraPaxRate());
            $price = $regularPrices
                ->where('min_pax', '<=', $paxCount)
                ->where('max_pax', '>=', $paxCount)
                ->where('min_age', '<=', $age)
                ->where('max_age', '>=', $age)
                ->sortBy(function ($price): int {
                    $ageRange = (int) $price->max_age - (int) $price->min_age;
                    $paxRange = (int) $price->max_pax - (int) $price->min_pax;

                    // Si plusieurs lignes correspondent, la tranche la plus
                    // précise doit passer avant une tranche générique.
                    return ($ageRange * 100000) + $paxRange;
                })
                ->first();

            if ($price) {
                $total = $this->usesGlobalGuidingRate($price)
                    ? (float) $price->price
                    : (float) $price->price * $paxCount;

                return [
                    'unit' => $total / max(1, $paxCount),
                    'total' => $total,
                ];
            }

            // Dans le guidage, une catégorie d'âge sans ligne tarifaire est
            // gratuite. Les adultes au-delà de la dernière tranche restent,
            // eux, éligibles au supplément par pax supplémentaire.
            if ((int) $this->category_id === 4) {
                $fallback = $regularPrices
                    ->where('min_age', '<=', $age)
                    ->where('max_age', '>=', $age)
                    ->sortByDesc('max_pax')
                    ->first();

                if (! $fallback) {
                    return ['unit' => 0.0, 'total' => 0.0];
                }

                $total = $this->baseRateTotal($fallback, $paxCount, $period, $age);

                return [
                    'unit' => $total / max(1, $paxCount),
                    'total' => $total,
                ];
            }

            return null;
        };

        $usePerAgePaxCount = (int) $this->category_id === 4;
        $childPrice = $children > 0 ? $findPrice($childAge, $usePerAgePaxCount ? $children : $totalPax) : ['unit' => 0.0, 'total' => 0.0];
        $teenPrice = $teenagers > 0 ? $findPrice($teenAge, $usePerAgePaxCount ? $teenagers : $totalPax) : ['unit' => 0.0, 'total' => 0.0];
        $adultPrice = $adults > 0 ? $findPrice($adultAge, $usePerAgePaxCount ? $adults : $totalPax) : ['unit' => 0.0, 'total' => 0.0];

        if (($children > 0 && $childPrice === null) || ($teenagers > 0 && $teenPrice === null) || ($adults > 0 && $adultPrice === null)) {
            return null;
        }

        return [
            'adult_unit' => $adultPrice['unit'],
            'child_unit' => $childPrice['unit'],
            'teen_unit' => $teenPrice['unit'],
            'adult_total' => $adultPrice['total'],
            'child_total' => $childPrice['total'],
            'teen_total' => $teenPrice['total'],
            'total' => $adultPrice['total'] + $childPrice['total'] + $teenPrice['total'],
        ];
    }

    /**
     * Compatibilité avec les appels existants qui ne nécessitent que le total.
     */
    public function calculateAgeBasedBaseTotal($period, int $totalPax, int $adults, int $children, int $teenagers = 0): ?float
    {
        $breakdown = $this->calculateAgeBasedBreakdown($period, $totalPax, $adults, $children, $teenagers);

        return $breakdown['total'] ?? null;
    }

    public function hasChildSelector(): bool
    {
        return (bool) ($this->attributes['enable_child_selector'] ?? false);
    }

    public function hasTeenSelector(): bool
    {
        return (bool) ($this->attributes['enable_teen_selector'] ?? false);
    }

    public function usesAgeSelector(): bool
    {
        return $this->hasChildSelector() || $this->hasTeenSelector() || (bool) ($this->attributes['enable_age_selector'] ?? false);
    }

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            if ($product->isDirty('enable_child_selector') || $product->isDirty('enable_teen_selector')) {
                $product->setAttribute('enable_age_selector', $product->hasChildSelector() || $product->hasTeenSelector());
            }
        });
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Les groupes d'agences autorisés à demander cette prestation. */
    public function clientGroups(): BelongsToMany
    {
        return $this->belongsToMany(ClientGroup::class);
    }

    /**
     * Une prestation sans groupe reste accessible à toutes les agences.
     */
    public function scopeAvailableForAgency(Builder $query, ?int $agencyId): Builder
    {
        $groupId = $agencyId ? Agency::query()->whereKey($agencyId)->value('client_group_id') : null;

        return $query->where(function (Builder $query) use ($groupId): void {
            $query->whereDoesntHave('clientGroups');

            if ($groupId) {
                $query->orWhereHas('clientGroups', fn (Builder $groups) => $groups->whereKey($groupId));
            }
        });
    }

    public function isAvailableForAgency(?int $agencyId): bool
    {
        if (! $this->clientGroups()->exists()) {
            return true;
        }

        $groupId = $agencyId ? Agency::query()->whereKey($agencyId)->value('client_group_id') : null;

        return $groupId !== null && $this->clientGroups()->whereKey($groupId)->exists();
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function productPeriods()
    {
        return $this->hasMany(ProductPeriod::class);
    }

    public function periodForDate(string|Carbon|null $date): ?ProductPeriod
    {
        if (blank($date)) {
            return null;
        }

        $periods = $this->relationLoaded('productPeriods')
            ? $this->productPeriods
            : $this->productPeriods()->get();

        return $periods->first(fn (ProductPeriod $period): bool => $period->matchesDate($date));
    }

    public function productOptions()
    {
        return $this->hasMany(ProductOption::class);
    }

    public function guidingOptionSeasonPrices()
    {
        return $this->hasMany(ProductOptionSeasonPrice::class);
    }

    public function productSuppliers()
    {
        return $this->hasMany(ProductSupplier::class);
    }
}
