<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Statistics extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static string | \UnitEnum | null $navigationGroup = 'Rapports & Analyses';
    protected static ?string $navigationLabel = 'Business Intelligence';
    protected static ?string $title = 'Analyse Financière & Statistiques';
    protected static ?string $slug = 'statistiques-bi';

    // 💡 LE CORRECTIF EST ICI : retrait du mot-clé "static" exigé par la classe parente
    protected string $view = 'filament.pages.statistics';

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\StatsOverview::class,
            \App\Filament\Widgets\AgencyPerformance::class,
            \App\Filament\Widgets\ProductPerformance::class,
            \App\Filament\Widgets\SupplierPerformance::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        // On force les widgets à prendre 100% de la largeur pour que les tableaux soient bien lisibles
        return 1;
    }
}