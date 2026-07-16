<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema; 
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section; 

class Statistics extends Dashboard 
{
    use HasFiltersForm; 

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static string | \UnitEnum | null $navigationGroup = 'Rapports & Analyses';
    protected static ?string $navigationLabel = 'Business Intelligence';
    protected static ?string $title = 'Analyse Financière & Statistiques';

    protected static string $routePath = 'statistiques-bi';

    public function filtersForm(Schema $form): Schema
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
                ->columns(2)
                ->columnSpanFull() // 💡 Force le bloc à s'étaler sur toute la largeur de l'écran
                ->compact(),       // 💡 Réduit les marges pour que le bloc prenne le moins de place possible en hauteur
        ]);
    }

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

    public function getColumns(): int | array
    {
        return 2;
    }
}