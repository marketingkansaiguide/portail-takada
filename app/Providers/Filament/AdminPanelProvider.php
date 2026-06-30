<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            
            // 🎨 Identité Visuelle (Logo SVG & Config)
            ->brandName('Portail Takada')
            ->brandLogo(asset('images/logo.svg')) 
            ->brandLogoHeight('3rem')             
            ->favicon(asset('images/logo.svg'))   
            
            // 🌍 Sélecteur de Langue Manuel dans le menu Utilisateur
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn () => app()->getLocale() === 'fr' ? '✓ Français' : 'Français')
                    ->url(fn () => route('lang.switch', ['locale' => 'fr']))
                    ->icon('heroicon-m-language'),
                MenuItem::make()
                    ->label(fn () => app()->getLocale() === 'en' ? '✓ English' : 'English')
                    ->url(fn () => route('lang.switch', ['locale' => 'en']))
                    ->icon('heroicon-m-language'),
                MenuItem::make()
                    ->label(fn () => app()->getLocale() === 'ja' ? '✓ 日本語' : '日本語')
                    ->url(fn () => route('lang.switch', ['locale' => 'ja']))
                    ->icon('heroicon-m-language'),
            ])
            
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class, // <-- Enregistrement de notre traducteur dynamique
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}