<?php

namespace App\Filament\Agency\Pages;

use App\Models\Product;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use BackedEnum; // 💡 Importation indispensable pour le typage strict PHP 8

class Catalogue extends Page
{
    // 💡 CORRECTION DU TYPE : Utilisation de la signature stricte demandée par votre version
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    // 💡 CORRECTION : Suppression du mot-clé "static" pour correspondre au typage de votre framework
    protected string $view = 'filament.agency.pages.catalogue';

    protected static ?string $title = 'Catalogue des Activités';

    public string $search = '';

    /**
     * Récupération dynamique des produits pour le front.
     * SÉCURITÉ : On ne montre que les produits publics aux visiteurs non-connectés.
     */
    public function getProductsProperty(): Collection
    {
        $query = Product::query();

        // Filtre de recherche
        if (!empty($this->search)) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        // Si non connecté, on cache les produits "privés" (is_public = false)
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