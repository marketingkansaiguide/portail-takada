<?php

namespace App\Providers\Filament;

use App\Models\Setting;
use App\Http\Middleware\SetLocale;
use App\Support\TakadaBrand;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Enums\GlobalSearchPosition;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\Facades\Storage;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        try {
            $settings = Setting::query()->first();
        } catch (\Throwable) {
            $settings = null;
        }
        $brandLogo = $settings?->site_logo_path
            ? Storage::disk('public')->url($settings->site_logo_path)
            : asset('images/logo.svg');
        $favicon = $settings?->site_favicon_path
            ? Storage::disk('public')->url($settings->site_favicon_path)
            : asset('images/logo.svg');

        return TakadaBrand::apply($panel)
            ->default()
            ->id('admin')
            ->path('admin')
            ->spa(true, true)
            ->databaseNotifications()
            ->globalSearch(position: GlobalSearchPosition::Topbar)
            ->globalSearchDebounce('300ms')
            ->globalSearchKeyBindings(['ctrl+k', 'command+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->login()
            
            // 🎨 Identité Visuelle (Logo SVG & Config)
            ->brandName('Portail Takada')
            ->brandLogo($brandLogo)
            ->brandLogoHeight('3rem')             
            ->favicon($favicon)
            
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
            
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.partials.admin-dark-mode-overrides')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.partials.page-navigation-feedback')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // 💡 AJOUT DU WIDGET GOOGLE CALENDAR ICI
                \App\Filament\Widgets\GoogleCalendarWidget::class,
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
