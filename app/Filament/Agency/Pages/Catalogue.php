<?php

namespace App\Filament\Agency\Pages;

use App\Models\Product;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use BackedEnum;

class Catalogue extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected string $view = 'filament.agency.pages.catalogue';

    // 💡 Conserve "Catalogue des Activités" uniquement pour l'onglet du navigateur
    protected static ?string $title = 'Catalogue des Activités';

    // 💡 SUPPRESSION DU TITRE H1 NATIF DE FILAMENT
    protected ?string $heading = '';

    public string $search = '';

    public function getProductsProperty(): Collection
    {
        // Chargement des prix saisonniers et catégories pour le catalogue
        $query = Product::with(['category', 'productPeriods.productPrices']);

        if (!empty($this->search)) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        // Si l'agence n'est pas connectée, on n'affiche que les produits publics
        if (!auth('agency')->check()) {
            $query->where('is_public', true);
        }

        return $query->orderBy('name')->get();
    }

    public static function getNavigationLabel(): string
    {
        return __('Catalogue');
    }
}