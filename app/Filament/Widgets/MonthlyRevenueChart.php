<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Folder;
use Carbon\Carbon;

class MonthlyRevenueChart extends ChartWidget
{
    protected static bool $isLazy = false; 
    protected ?string $heading = 'Évolution du CA (12 derniers mois)';
    protected int | string | array $columnSpan = 1;

    // 💡 On impose la même hauteur réduite que le camembert
    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->startOfMonth()->subMonths($i);
            $labels[] = ucfirst($date->translatedFormat('M Y')); 

            $sum = Folder::whereIn('status', ['confirmed', 'completed'])
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('total_price');

            $data[] = $sum;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Chiffre d\'Affaires (¥)',
                    'data' => $data,
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    // 💡 Force le graphique à respecter les 260px sans déformer la page
    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
        ];
    }
}