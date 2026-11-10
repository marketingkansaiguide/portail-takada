<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetLocale;
use App\Models\Setting;
use App\Support\TakadaBrand;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Espace commercial Takada (/commercial) : dossiers internes et dossiers agence du
 * périmètre du commercial, préparation et commande des prestations. Séparé de
 * l'administration ; mêmes modèles, règles d'accès et moteur tarifaire.
 */
class CommercialPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        try {
            $settings = Setting::query()->first();
        } catch (\Throwable) {
            $settings = null;
        }
        $brandLogo = $settings?->site_logo_path ? Storage::disk('public')->url($settings->site_logo_path) : asset('images/logo.svg');
        $favicon = $settings?->site_favicon_path ? Storage::disk('public')->url($settings->site_favicon_path) : asset('images/logo.svg');

        return TakadaBrand::apply($panel)
            ->id('commercial')
            ->path('commercial')
            ->spa(true, true)
            ->login()
            ->profile(\App\Filament\Auth\EditProfile::class, isSimple: false)
            ->databaseNotifications()
            ->brandName('Takada — Espace commercial')
            ->brandLogo($brandLogo)
            ->brandLogoHeight('3rem')
            ->favicon($favicon)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('filament.partials.admin-dark-mode-overrides')->render())
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => view('filament.partials.page-navigation-feedback')->render())
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => '<style>.fi-ta-cell:not(:has(.fi-ta-actions)) { padding-block: .25rem; } .fi-ta-header-cell, .fi-ta-cell { padding-inline: .5rem !important; } .fi-ta-cell .fi-badge { max-width: none; } .fi-ta-cell .fi-badge .fi-badge-label { white-space: normal !important; overflow: visible !important; text-overflow: clip !important; }</style>')
            ->resources([
                \App\Filament\Commercial\Resources\CommercialFolderResource::class,
            ])
            ->pages([
                \App\Filament\Commercial\Pages\CommercialDashboard::class,
            ])
            ->widgets([
                \App\Filament\Commercial\Widgets\CommercialStats::class,
                \App\Filament\Commercial\Widgets\FoldersToFollow::class,
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
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
