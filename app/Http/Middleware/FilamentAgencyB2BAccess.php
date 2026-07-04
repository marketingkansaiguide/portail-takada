<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

class FilamentAgencyB2BAccess
{
    /**
     * Filtre d'accès dynamique pour le catalogue public et l'espace privé de l'agence.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Si l'agence est connectée, on délègue au middleware natif de Filament
        if (auth('agency')->check()) {
            return app(FilamentAuthenticate::class)->handle($request, $next);
        }

        // 2. Si c'est un visiteur anonyme (non connecté) :
        $route = $request->route();
        if ($route) {
            $routeName = $route->getName();
            
            // 💡 LISTE BLANCHE ENRICHIE : On autorise la consultation de la fiche produit publique
            $publicRoutes = [
                'filament.agency.pages.catalogue',    // La liste du catalogue
                'filament.agency.pages.view-product', // La fiche produit détaillée
                'filament.agency.auth.login',         // La page de connexion
                'filament.agency.auth.logout',        // La déconnexion
            ];

            // On autorise l'accès si la route est publique ou s'il s'agit de requêtes Livewire (indispensables au catalogue)
            if (in_array($routeName, $publicRoutes) || str_contains($request->path(), 'livewire')) {
                return $next($request);
            }
        }

        // 3. Si le visiteur anonyme tape la racine du site ('/'), on l'envoie vers le catalogue
        if ($request->is('/') || $request->path() === '' || $request->path() === '/') {
            return redirect()->route('filament.agency.pages.catalogue');
        }

        // 4. Pour tout le reste (accès forcé à l'espace privé), on exige le login
        return redirect()->route('filament.agency.auth.login');
    }
}