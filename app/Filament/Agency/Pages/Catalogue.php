<?php

namespace App\Filament\Agency\Pages;

use App\Models\Product;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Catalogue extends Page
{
    // Typage strict pour correspondre à la classe parente
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    
    // Le nom dans le menu
    protected static ?string $navigationLabel = 'Catalogue des offres';
    
    // CORRECTION ICI : La classe parente exige exactement le type ?string
    protected static ?string $title = 'Catalogue Billetterie, Transports & Activités';
    
    // Un slug vide indique que c'est la page d'accueil de ce panel (/)
    protected static ?string $slug = ''; 

    // La propriété $view ne doit PAS être statique
    protected string $view = 'filament.agency.pages.catalogue';

    /**
     * Récupère tous les produits pour les envoyer à la vue.
     */
    public function getProductsProperty()
    {
        return Product::with('category')->get();
    }

    /**
     * Vérifie si l'utilisateur actuel est connecté (une agence) ou non.
     */
    public function getIsAuthenticatedProperty()
    {
        return Auth::check();
    }
}