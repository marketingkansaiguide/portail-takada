<?php

namespace App\Providers\Filament;

use App\Filament\Agency\Pages\Catalogue;
use App\Filament\Agency\Pages\Contact; // 💡 1. IMPORT AJOUTÉ ICI
use App\Filament\Agency\Resources\AgencyFolderResource;
use App\Filament\Agency\Resources\AgencyUserResource;
use App\Http\Middleware\FilamentAgencyB2BAccess;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Filament\View\PanelsRenderHook;
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
            ->path('') // Racine du site
            ->login()
            ->authGuard('agency') // Guard de session isolé pour les agences
            ->darkMode(false) // DÉSACTIVATION DU MODE SOMBRE : Retire le bouton de sélection d'affichage du front-office
            ->colors([
                'primary' => Color::hex('#096a61'),
                'secondary' => Color::hex('#dde8b9'),
                'gray' => Color::Slate,
            ])
            ->topNavigation()
            ->breadcrumbs(false)
            ->brandName('Portail Agences Takada')
            ->brandLogo(asset('images/logo.svg'))
            ->brandLogoHeight('3rem')
            
            ->discoverResources(in: app_path('Filament/Agency/Resources'), for: 'App\\Filament\\Agency\\Resources')
            
            // 💡 DÉCLARATION EXPLICITE DES RESSOURCES POUR FORCER L'AFFICHAGE DANS LE MENU
            ->resources([
                AgencyFolderResource::class,
                AgencyUserResource::class,
            ])
            
            ->discoverPages(in: app_path('Filament/Agency/Pages'), for: 'App\\Filament\\Agency\\Pages')
            ->pages([
                Catalogue::class,
                Contact::class, // 💡 2. DÉCLARATION EXPLICITE AJOUTÉE POUR GÉNÉRER LA ROUTE
            ])
            ->discoverWidgets(in: app_path('Filament/Agency/Widgets'), for: 'App\\Filament\\Agency\\Widgets')
            ->widgets([])
            
            // DESIGN & UX : Injection dynamique du bouton de connexion en haut à droite
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => auth('agency')->guest()
                    ? '<a href="' . route('filament.agency.auth.login') . '" class="inline-flex items-center justify-center gap-1.5 font-semibold shrink-0 transition duration-75 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:pointer-events-none disabled:opacity-50 dark:hover:bg-white/5 fi-btn fi-btn-size-md fi-btn-color-primary fi-color-custom bg-primary-600 text-white shadow-sm hover:bg-primary-500 focus-visible:outline-primary-600 rounded-lg px-4 py-2 text-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" /></svg>
                        Connexion Agence
                       </a>'
                    : ''
            )
            
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
                FilamentAgencyB2BAccess::class,
            ]);
    }
}