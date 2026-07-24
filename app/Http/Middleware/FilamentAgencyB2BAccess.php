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
            
            // 💡 ON MET À JOUR LE NOM DE LA ROUTE ICI
            $publicRoutes = [
                'filament.agency.pages.catalogue',    
                'filament.agency.pages.view-product', 
                'filament.agency.pages.assistance-sur-mesure', // Nouvelle route
                'filament.agency.auth.login',         
                'filament.agency.auth.logout',        
            ];

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