<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Folder;

class PaxDemographicsChart extends ChartWidget
{
    protected static bool $isLazy = false; 
    protected ?string $heading = 'Répartition Adultes / Enfants';
    protected int | string | array $columnSpan = 1;
    
    // 💡 Exactement la même hauteur que le graphique CA
    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $adults = Folder::whereIn('status', ['confirmed', 'completed'])->sum('pax_adults');
        $children = Folder::whereIn('status', ['confirmed', 'completed'])->sum('pax_children');

        return [
            'datasets' => [
                [
                    'label' => 'Voyageurs',
                    'data' => [$adults, $children],
                    'backgroundColor' => ['#3b82f6', '#10b981'], 
                ],
            ],
            'labels' => ['Adultes', 'Enfants (-18 ans)'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '75%', // Anneau fin
            'plugins' => [
                'legend' => [
                    'position' => 'right', // Légende sur le côté pour gagner de la hauteur
                ],
            ],
        ];
    }
}