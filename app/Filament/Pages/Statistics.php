<?php

namespace App\Filament\Pages;

use App\Support\AdminAccess;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema; 
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section; 

class Statistics extends Dashboard 
{
    use HasFiltersForm; 

    public static function canAccess(): bool
    {
        return AdminAccess::allows('operations.statistics');
    }

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static string | \UnitEnum | null $navigationGroup = 'Rapports & Analyses';
    protected static ?string $navigationLabel = 'Business Intelligence';
    protected static ?string $title = null;

    public function getTitle(): string
    {
        return __('Analyse Financière & Statistiques');
    }

    protected static string $routePath = 'statistiques-bi';

    public function filtersForm(Schema $form): Schema
    {
        return $form->schema([
            Section::make(__('Période d\'analyse'))
            ->description(__('Tous les indicateurs ci-dessous utilisent la date de création des dossiers. Sans filtre, la synthèse couvre tout l’historique et le graphique présente les 12 derniers mois.'))
                ->schema([
                    DatePicker::make('startDate')
                ->label(__('Du'))
                        ->native(false)
                        ->maxDate(now()),
                        
                    DatePicker::make('endDate')
                ->label(__('Au'))
                        ->native(false)
                        ->maxDate(now()),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->compact(),
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
