<?php

namespace App\Filament\Agency\Pages;

use App\Models\Product;
use App\Models\Setting;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use BackedEnum;

class Catalogue extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected string $view = 'filament.agency.pages.catalogue';

    // 💡 Conserve "Catalogue des Activités" uniquement pour l'onglet du navigateur
    protected static ?string $title = null;

    public function getTitle(): string
    {
        return __('Catalogue des Activités');
    }

    // 💡 SUPPRESSION DU TITRE H1 NATIF DE FILAMENT
    protected ?string $heading = '';

    public string $search = '';

    #[Url(as: 'category')]
    public ?int $categoryId = null;

    private const PRODUCTS_PER_CATEGORY = 4;

    public function getCatalogueSettingsProperty(): array
    {
        $settings = Setting::query()->first();

        return [
            'separateCategories' => $settings?->catalogue_categories_separate ?? true,
            'useCarousel' => $settings?->catalogue_use_carousel ?? true,
            'productsPerCategory' => max(1, min(24, (int) ($settings?->catalogue_products_per_category ?? self::PRODUCTS_PER_CATEGORY))),
        ];
    }

    public function getProductsProperty(): Collection
    {
        // Chargement des prix saisonniers et catégories pour le catalogue
        $query = Product::with(['category', 'productPeriods.productPrices']);

        if (!empty($this->search)) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

        $agencyId = auth('agency')->user()?->agency_id
            ?? \Filament\Facades\Filament::auth()->user()?->agency_id;

        // Si l'agence n'est pas connectée, on n'affiche que les produits publics
        if (! $agencyId) {
            $query->where('is_public', true);
        }

        $query->availableForAgency($agencyId);

        return $query->orderBy('name')->get();
    }

    /**
     * Regroupe l'accueil du catalogue par catégorie. Hors recherche ou filtre
     * de catégorie, chaque bloc se limite à un aperçu de quatre prestations.
     */
    public function getProductSectionsProperty(): Collection
    {
        $settings = $this->catalogueSettings;
        $showAllProducts = filled($this->search) || $this->categoryId !== null;

        if (! $settings['separateCategories']) {
            return collect([[
                'categoryId' => null,
                'name' => __('Toutes les prestations'),
                'products' => $this->products,
                'total' => $this->products->count(),
            ]]);
        }

        return $this->products
            ->groupBy(fn (Product $product) => $product->category?->id ?? 'uncategorized')
            ->map(function (Collection $products) use ($showAllProducts, $settings): array {
                $category = $products->first()->category;

                return [
                    'categoryId' => $category?->id,
                    'name' => $category?->name ?? __('Autres prestations'),
                    'products' => $showAllProducts ? $products : $products->take($settings['productsPerCategory']),
                    'total' => $products->count(),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    public static function getNavigationLabel(): string
    {
        return __('Catalogue');
    }
}
