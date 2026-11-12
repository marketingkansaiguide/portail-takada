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
        'important_documents' => 'array',
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

    /** Libellé de la date de prestation (ex. « Date de récupération » pour une location). */
    public function dateLabel(string $default): string
    {
        return filled($this->date_label) ? $this->date_label : $default;
    }

    /** Libellé de la quantité (ex. « Nombre de jours » pour une location). */
    public function quantityLabel(string $default): string
    {
        return filled($this->quantity_label) ? $this->quantity_label : $default;
    }

    /** Multiplicateur facultatif (ex. « Nombre de boîtiers » pour une location). */
    public function usesMultiplier(): bool
    {
        return filled($this->multiplier_label);
    }

    /** Nom affiché dans le détail du prix (« Nombre de boîtiers » → « boîtiers »). */
    public function multiplierNoun(): string
    {
        return mb_strtolower(trim(preg_replace("/^nombre\\s+(de|d')\\s*/iu", '', (string) $this->multiplier_label)));
    }

    public function multiplierFrom(?array $customValues): int
    {
        return $this->usesMultiplier() ? max(1, (int) ($customValues['_multiplier'] ?? 1)) : 1;
    }

    public function isTransport(): bool
    {
        return $this->product_type === 'transport';
    }

    /** PA guide du tarif de base pour la date et le nombre de pax (hors options). */
    public function guideBasePurchaseForDate(string|Carbon|null $date, int $quantity): ?float
    {
        if ((int) $this->category_id !== 4 || blank($date) || ! ($period = $this->periodForDate($date))) {
            return null;
        }

        $regular = $period->productPrices->reject(fn (ProductPrice $price): bool => $price->isExtraPaxRate());
        $row = $regular->where('min_pax', '<=', $quantity)->where('max_pax', '>=', $quantity)->first()
            ?? $regular->sortByDesc('max_pax')->first();

        if (! $row || $row->purchase_price === null) {
            return null;
        }

        $total = (float) $row->purchase_price;
        foreach ($period->productPrices->filter(fn (ProductPrice $price): bool => $price->isExtraPaxRate()) as $extraRate) {
            $from = max(1, (int) $extraRate->min_pax);
            $to = max($from, (int) ($extraRate->max_pax ?? $quantity));
            $total += max(0, min($quantity, $to) - $from + 1) * (float) ($extraRate->purchase_price ?? 0);
        }

        return $total;
    }

    /**
     * Tranche « forfait global » : le prix de la ligne couvre tout le groupe,
     * quel que soit le nombre de pax de la tranche.
     */
    public function usesGlobalRate(?ProductPrice $price): bool
    {
        return $price?->isGlobalRate() ?? false;
    }

    public function baseRateTotal(?ProductPrice $price, int $quantity, ?ProductPeriod $period = null, ?int $age = null): float
    {
        if (! $price) {
            return 0.0;
        }

        $baseTotal = $this->usesGlobalRate($price)
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
     * Nombre de prestations de dossiers qui utilisent ce produit. La base supprime en cascade les
     * prestations d'un produit supprimé : un produit utilisé ne doit donc jamais être supprimé.
     */
    public function usageCount(): int
    {
        return \App\Models\FolderItem::where('product_id', $this->id)->count();
    }

    /**
     * Miniature légère de la première image (listes de l'admin) : évite de télécharger
     * les originaux de plusieurs Mo pour afficher une vignette de 48 px.
     */
    public function thumbnailUrl(int $width = 120): ?string
    {
        $images = $this->images;
        if (is_string($images)) {
            $images = json_decode($images, true);
        }
        $first = is_array($images) ? collect($images)->filter(fn ($i) => filled($i))->first() : null;
        if (! is_string($first)) {
            return null;
        }
        $first = trim($first);
        if (str_starts_with($first, 'http://') || str_starts_with($first, 'https://')) {
            return $first;
        }
        $path = preg_replace('#^/?storage/#', '', $first);

        return \App\Support\ImageOptimizer::thumbUrl($path, $width);
    }

    /**
     * Documents « Informations importantes » du produit, avec leur URL publique.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public function importantDocumentLinks(): array
    {
        return collect($this->important_documents ?? [])
            ->filter(fn ($doc): bool => ! empty($doc['file']) || filled($doc['label'] ?? null))
            ->map(fn ($doc): array => [
                'label' => filled($doc['label'] ?? null) ? $doc['label'] : basename($doc['file']),
                'url' => ! empty($doc['file']) ? Storage::disk('public')->url($doc['file']) : null,
            ])
            ->values()
            ->all();
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

        $findPrice = function (int $age, int $paxCount, ?int $groupCount = null) use ($prices, $period): ?array {
            // $paxCount sert à choisir la tranche ; $groupCount = nombre de personnes de cette catégorie.
            $groupCount ??= $paxCount;
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
                $total = $this->usesGlobalRate($price)
                    ? (float) $price->price
                    : (float) $price->price * $groupCount;

                return [
                    'unit' => $total / max(1, $groupCount),
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

                $total = $this->baseRateTotal($fallback, $groupCount, $period, $age);

                return [
                    'unit' => $total / max(1, $groupCount),
                    'total' => $total,
                ];
            }

            return null;
        };

        $usePerAgePaxCount = (int) $this->category_id === 4;
        $childPrice = ['unit' => 0.0, 'total' => 0.0];
        if ($children > 0) {
            $childPax = $usePerAgePaxCount ? $children : $totalPax;
            // Sans tranche « 0 an » (âge minimum du produit), on cherche la
            // tranche enfant la plus haute plutôt que de facturer au tarif adulte.
            $childPrice = $findPrice($childAge, $childPax, $children)
                ?? $findPrice(max(0, (int) ($this->child_age_limit ?? 11)), $childPax, $children);
        }
        $teenPrice = $teenagers > 0 ? $findPrice($teenAge, $usePerAgePaxCount ? $teenagers : $totalPax, $teenagers) : ['unit' => 0.0, 'total' => 0.0];
        $adultPrice = $adults > 0 ? $findPrice($adultAge, $usePerAgePaxCount ? $adults : $totalPax, $adults) : ['unit' => 0.0, 'total' => 0.0];

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

    /** Produits actifs (is_public) : seuls ceux-là sont proposés aux agences et aux nouveaux dossiers. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_public'), true);
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

    /** Grille tarifaire de base (hors variantes liées à une option). */
    public function productPeriods()
    {
        return $this->hasMany(ProductPeriod::class)->whereNull('variant_option_id');
    }

    /** Grilles qui remplacent la grille de base quand leur option est choisie (ex: cérémonie privée). */
    public function variantPeriods()
    {
        return $this->hasMany(ProductPeriod::class)->whereNotNull('variant_option_id');
    }

    protected ?\Illuminate\Support\Collection $baseTariffPeriods = null;

    /** @var array<string, \Illuminate\Support\Collection> */
    protected array $variantPeriodsCache = [];

    /**
     * Charge la grille tarifaire correspondant aux options choisies : celle de
     * la variante si une option qui en porte une est sélectionnée, sinon la
     * grille de base. À appeler avant tout calcul de prix.
     *
     * @param  array<int, int|string|null>  $optionIds
     */
    public function applyTariffVariant(array $optionIds): static
    {
        if (! $this->relationLoaded('productPeriods')) {
            $this->load('productPeriods.productPrices');
        }

        $this->baseTariffPeriods ??= $this->getRelation('productPeriods');

        $optionIds = array_values(array_filter(array_map('intval', $optionIds)));
        sort($optionIds);
        $variant = $optionIds === []
            ? collect()
            : ($this->variantPeriodsCache[implode(',', $optionIds)] ??= $this->variantPeriods()->whereIn('variant_option_id', $optionIds)->with('productPrices')->get());

        $this->setRelation('productPeriods', $variant->isNotEmpty() ? $variant : $this->baseTariffPeriods);

        return $this;
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
        return $this->hasMany(ProductOption::class)->orderBy('sort_order')->orderBy('id');
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
