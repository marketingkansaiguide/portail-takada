<?php

namespace App\Support;

use Filament\Enums\ThemeMode;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;

/**
 * Identité graphique Takada Travel partagée par les panels admin et agence :
 * thème clair « papier » par défaut, thème foncé « marine » proposé via un bouton mis en avant.
 */
class TakadaBrand
{
    /** Rouge du logo 旅 (#c91f37) placé au rang 600, utilisé pour les boutons dans les deux thèmes. */
    public const RED = [
        50 => '#fdf2f3',
        100 => '#fbe3e6',
        200 => '#f6c6cd',
        300 => '#ee9aa6',
        400 => '#e2667a',
        500 => '#d43a52',
        600 => '#c91f37',
        700 => '#a8172d',
        800 => '#8b1628',
        900 => '#741626',
        950 => '#410811',
    ];

    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->defaultThemeMode(ThemeMode::Light)
            // Logo des supports marine : 旅 bleu sur carré vert pâle, texte vert pâle (illisible sur fond clair).
            ->darkModeBrandLogo(asset('images/logo-dark.svg'))
            ->colors([
                'primary' => self::RED,
                // Gris chauds proches du gris des cartes de visite (#706c67) ; remplacés par le marine en thème foncé.
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
