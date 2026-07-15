<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;

class Statistics extends Dashboard // 💡 C'est maintenant un Dashboard officiel !
{
    use HasFiltersForm; // 💡 Active les filtres partagés entre tous les widgets

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static string | \UnitEnum | null $navigationGroup = 'Rapports & Analyses';
    protected static ?string $navigationLabel = 'Business Intelligence';
    protected static ?string $title = 'Analyse Financière & Statistiques';
    protected static ?string $slug = 'statistiques-bi';

    // 💡 Le fameux formulaire de filtre global
    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Section::make('Période d\'analyse')
                ->description('Filtrez toutes les statistiques (basé sur la date de création des dossiers). Les indicateurs globaux compareront automatiquement avec la période précédente.')
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Du (inclus)')
                        ->native(false)
                        ->maxDate(now()),
                        
                    DatePicker::make('endDate')
                        ->label('Au (inclus)')
                        ->native(false)
                        ->maxDate(now()),
                ])
                ->columns(2),
        ]);
    }

    // 💡 Avec un Dashboard, on utilise getWidgets() au lieu de getHeaderWidgets()
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\StatsOverview::class,
            \App\Filament\Widgets\MonthlyRevenueChart::class,
            \App\Filament\Widgets\PaxDemographicsChart::class,
            \App\Filament\Widgets\FolderPerformance::class,
            \App\Filament\Widgets\CategoryPerformance::class,
            \App\Filament\Widgets\AgencyPerformance::class,
            \App\Filament\Widgets\ProductPerformance::class,
            \App\Filament\Widgets\SupplierPerformance::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return 2;
    }
}