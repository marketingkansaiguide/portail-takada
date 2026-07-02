<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AgencyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('agency')
            ->path('') // CLÉ : Une chaîne vide indique à Filament de s'installer directement sur la racine (/)
            ->login() // L'écran de connexion devient automatiquement accessible sur la route /login
            ->colors([
                // Configuration de votre charte graphique personnalisée
                'primary' => Color::hex('#096a61'),   // Couleur principale
                'secondary' => Color::hex('#dde8b9'), // Touches de rappel
                'gray' => Color::Slate,               // Teinte de gris neutre
            ])
            
            // --- CONFIGURATION LOOK & FEEL FRONT-OFFICE ---
            ->topNavigation() // Active la navigation horizontale haute, supprime la sidebar "admin"
            ->breadcrumbs(false) // Masque les fils d'Ariane
            ->brandName('Portail Agences Takada')
            ->brandLogo(asset('images/logo.svg')) // Votre logo d'entreprise
            ->brandLogoHeight('3rem')
            
            // Configuration des dossiers de stockage pour le panel agence
            ->discoverResources(in: app_path('Filament/Agency/Resources'), for: 'App\\Filament\\Agency\\Resources')
            ->discoverPages(in: app_path('Filament/Agency/Pages'), for: 'App\\Filament\\Agency\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Agency/Widgets'), for: 'App\\Filament\\Agency\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class, // Gestion de la sécurité et de la session active
            ]);
    }
}