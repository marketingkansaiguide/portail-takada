<?php

namespace App\Support;

use Filament\Enums\ThemeMode;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;

/**
 * Identité graphique Takada Travel partagée par les panels admin et agence :
 * thème clair « papier » par défaut, thème foncé « nuit » proposé via un bouton mis en avant.
 */
class TakadaBrand
{
    /** Rouge laque inspiré du logo 旅, placé au rang 600 pour les boutons dans les deux thèmes. */
    public const RED = [
        50 => '#fbf3f4',
        100 => '#f6e4e6',
        200 => '#ecc5ca',
        300 => '#dd9aa3',
        400 => '#c96877',
        500 => '#b9404f',
        600 => '#a8293b',
        700 => '#8c2131',
        800 => '#731d2a',
        900 => '#5f1b26',
        950 => '#350b12',
    ];

    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->defaultThemeMode(ThemeMode::Light)
            // Logo pour fond foncé : 旅 bleu sur carré vert pâle, texte vert pâle (illisible sur fond clair).
            ->darkModeBrandLogo(asset('images/logo-dark.svg'))
            ->colors([
                'primary' => self::RED,
                // Gris chauds proches du gris des cartes de visite (#706c67) ; remplacés par la palette nuit en thème foncé.
                'gray' => Color::Stone,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.partials.brand-theme')->render(),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('filament.partials.theme-toggle')->render(),
            );
    }
}
