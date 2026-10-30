<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    protected $fillable = [
        'product_id',
        'group_name',
        'name',
        'code',
        'guide_city',
        'sort_order',
        'available_days',
        'guide_start_time',
        'guide_end_time',
        'price_modifier',
        'adult_price_modifier',
        'child_price_modifier',
        'teen_price_modifier',
        'billing_type',
        'is_required',
    ];

    protected $casts = [
        'price_modifier' => 'integer',
        'adult_price_modifier' => 'integer',
        'child_price_modifier' => 'integer',
        'teen_price_modifier' => 'integer',
        'is_required' => 'boolean',
        'available_days' => 'array',
    ];

    public function priceModifierForAge(bool $isChild): float
    {
        $agePrice = $isChild ? $this->child_price_modifier : $this->adult_price_modifier;

        return $agePrice !== null
            ? (float) $agePrice
            : (float) ($this->price_modifier ?? 0);
    }

    public function priceModifierForAgeGroup(string $group): float
    {
        $agePrice = match ($group) {
            'child' => $this->child_price_modifier,
            'teen' => $this->teen_price_modifier,
            default => $this->adult_price_modifier,
        };

        return $agePrice !== null ? (float) $agePrice : (float) ($this->price_modifier ?? 0);
    }

    /** Les tarifs saisonniers d'option s'appliquent aux activités de guidage et à toute option qui en porte (ex: transferts aéroport). */
    protected function usesSeasonalPricing(?Product $product): bool
    {
        if ((int) ($product?->category_id ?? 0) === 4) {
            return true;
        }

        return $this->relationLoaded('seasonalPrices')
            ? $this->seasonalPrices->isNotEmpty()
            : $this->seasonalPrices()->exists();
    }

    public function priceModifierForDate(string|null $date, string $group = 'adult', ?int $quantity = null): float
    {
        $fallback = $this->priceModifierForAgeGroup($group);
        $product = $this->relationLoaded('product') ? $this->product : $this->product()->with('productPeriods')->first();

        if (! $this->usesSeasonalPricing($product) || blank($date)) {
            return $fallback;
        }

        $period = $product->periodForDate($date);
        if (! $period) {
            return $fallback;
        }

        $seasonalPrices = $this->relationLoaded('seasonalPrices')
            ? $this->seasonalPrices->where('product_period_id', $period->id)
            : $this->seasonalPrices()->where('product_period_id', $period->id)->get();

        $pricingAge = $this->ageForGroup($product, $group);
        $eligiblePrices = $period->productPrices
            ->filter(fn (ProductPrice $price): bool => (int) $price->min_age <= $pricingAge && (int) $price->max_age >= $pricingAge);

        $priceId = null;
        if ($quantity !== null) {
            $extraPrice = $eligiblePrices
                ->filter(fn (ProductPrice $price): bool => $price->isExtraPaxRate())
                ->where('min_pax', '<=', $quantity)
                ->where('max_pax', '>=', $quantity)
                ->sortByDesc('min_pax')
                ->first();
            $regularPrices = $eligiblePrices->reject(fn (ProductPrice $price): bool => $price->isExtraPaxRate());
            $basePrice = $extraPrice ?? ($regularPrices
                ->where('min_pax', '<=', $quantity)
                ->where('max_pax', '>=', $quantity)
                ->first() ?? $regularPrices->sortByDesc('max_pax')->first());
            $priceId = $basePrice?->id;
        }

        $seasonPrice = $priceId
            ? $seasonalPrices->firstWhere('product_price_id', $priceId)
            : null;
        $seasonPrice ??= $seasonalPrices->first(fn (ProductOptionSeasonPrice $price): bool => $price->product_price_id === null);

        $column = match ($group) {
            'child' => 'child_price_modifier',
            'teen' => 'teen_price_modifier',
            default => 'adult_price_modifier',
        };

        return $seasonPrice?->{$column} !== null
            ? (float) $seasonPrice->{$column}
            : ($seasonPrice?->price_modifier !== null ? (float) $seasonPrice->price_modifier : $fallback);
    }

    public function purchasePriceForDate(string|null $date, ?int $quantity = null): ?float
    {
        $product = $this->relationLoaded('product') ? $this->product : $this->product()->with('productPeriods.productPrices')->first();
        if (! $this->usesSeasonalPricing($product) || blank($date)) {
            return null;
        }
        $period = $product->periodForDate($date);
        if (! $period) {
            return null;
        }

        $seasonalPrices = $this->relationLoaded('seasonalPrices')
            ? $this->seasonalPrices->where('product_period_id', $period->id)
            : $this->seasonalPrices()->where('product_period_id', $period->id)->get();
        $regularPrices = $period->productPrices
            ->reject(fn (ProductPrice $price): bool => $price->isExtraPaxRate());
        $basePrice = $quantity !== null
            ? ($regularPrices
                ->where('min_pax', '<=', $quantity)
                ->where('max_pax', '>=', $quantity)
                ->first() ?? $regularPrices->sortByDesc('max_pax')->first())
            : $regularPrices->first();
        $seasonPrice = $basePrice
            ? $seasonalPrices->firstWhere('product_price_id', $basePrice->id)
            : null;
        $seasonPrice ??= $seasonalPrices->first(fn (ProductOptionSeasonPrice $price): bool => $price->product_price_id === null);

        // Écart de PA de la déclinaison, à ajouter au PA du tarif de base
        // (Product::guideBasePurchaseForDate).
        return (float) ($seasonPrice?->purchase_price_modifier ?? 0);
    }

    /**
     * Returns the number of pax to which a per-pax option is billed.
     * For a "supplement per additional pax" tier, only pax above the
     * highest preceding regular tier are billable.
     */
    public function billableQuantityForDate(string|null $date, ?int $quantity = null, string $group = 'adult'): int
    {
        $quantity = max(1, (int) ($quantity ?? 1));
        $product = $this->relationLoaded('product') ? $this->product : $this->product()->with('productPeriods')->first();

        if (! $this->usesSeasonalPricing($product) || blank($date)) {
            return $quantity;
        }

        $period = $product->periodForDate($date);
        if (! $period) {
            return $quantity;
        }

        $pricingAge = $this->ageForGroup($product, $group);
        $eligiblePrices = $period->productPrices
            ->filter(fn (ProductPrice $price): bool => (int) $price->min_age <= $pricingAge && (int) $price->max_age >= $pricingAge);

        $extraPrice = $eligiblePrices
            ->filter(fn (ProductPrice $price): bool => $price->isExtraPaxRate())
            ->where('min_pax', '<=', $quantity)
            ->where('max_pax', '>=', $quantity)
            ->sortByDesc('min_pax')
            ->first();

        if (! $extraPrice) {
            return $quantity;
        }

        $highestRegularPax = $eligiblePrices
            ->reject(fn (ProductPrice $price): bool => $price->isExtraPaxRate())
            ->max('max_pax');

        return max(1, $quantity - (int) ($highestRegularPax ?? 0));
    }

    /**
     * A seasonal modifier attached to an extra-pax tier is itself a per-pax
     * modifier, even when the base option is normally billed per booking.
     */
    public function hasExtraPaxSeasonalModifierForDate(string|null $date, ?int $quantity = null, string $group = 'adult'): bool
    {
        $quantity = max(1, (int) ($quantity ?? 1));
        $product = $this->relationLoaded('product') ? $this->product : $this->product()->with('productPeriods')->first();

        if (! $this->usesSeasonalPricing($product) || blank($date)) {
            return false;
        }

        $period = $product->periodForDate($date);
        if (! $period) {
            return false;
        }

        $pricingAge = $this->ageForGroup($product, $group);
        $extraPrice = $period->productPrices
            ->filter(fn (ProductPrice $price): bool => (int) $price->min_age <= $pricingAge && (int) $price->max_age >= $pricingAge)
            ->filter(fn (ProductPrice $price): bool => $price->isExtraPaxRate())
            ->where('min_pax', '<=', $quantity)
            ->where('max_pax', '>=', $quantity)
            ->sortByDesc('min_pax')
            ->first();

        if (! $extraPrice) {
            return false;
        }

        $seasonalPrices = $this->relationLoaded('seasonalPrices')
            ? $this->seasonalPrices->where('product_period_id', $period->id)
            : $this->seasonalPrices()->where('product_period_id', $period->id)->get();

        return $seasonalPrices->contains(
            fn (ProductOptionSeasonPrice $price): bool => (int) $price->product_price_id === (int) $extraPrice->id,
        );
    }

    /** @return array{adults: int, children: int, teenagers: int} */
    public function billableAgeGroupQuantitiesForDate(
        string|null $date,
        int $quantity,
        int $adults,
        int $children,
        int $teenagers,
    ): array {
        return [
            'adults' => $adults > 0 ? $this->billableQuantityForDate($date, $adults, 'adult') : 0,
            'children' => $children > 0 ? $this->billableQuantityForDate($date, $children, 'child') : 0,
            'teenagers' => $teenagers > 0 ? $this->billableQuantityForDate($date, $teenagers, 'teen') : 0,
        ];
    }

    protected function ageForGroup(Product $product, string $group): int
    {
        return match ($group) {
            'child' => 0,
            'teen' => (int) ($product->child_age_limit ?? 11) + 1,
            default => $product->teen_age_limit !== null
                ? (int) $product->teen_age_limit + 1
                : (int) ($product->child_age_limit ?? 11) + 1,
        };
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function seasonalPrices()
    {
        return $this->hasMany(ProductOptionSeasonPrice::class);
    }
}
