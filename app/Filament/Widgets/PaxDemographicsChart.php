<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\Folder;

class PaxDemographicsChart extends ChartWidget
{
    public static function canView(): bool
    {
        return \App\Support\AdminAccess::allows('folder.viewAny');
    }

    use InteractsWithPageFilters;

    protected static bool $isLazy = false; 
    protected static bool $isDiscovered = false;
    protected ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('Répartition des voyageurs');
    }
    protected int | string | array $columnSpan = 1;
    
    // 💡 Exactement la même hauteur que le graphique CA
    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $query = Folder::whereIn('status', ['confirmed', 'completed'])
            ->when($startDate, fn ($query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->whereDate('created_at', '<=', $endDate));

        $adults = (clone $query)->sum('pax_adults');
        $children = (clone $query)->sum('pax_children');

        return [
            'datasets' => [
                [
                    'label' => 'Voyageurs',
                    'data' => [$adults, $children],
                    'backgroundColor' => ['#3b82f6', '#10b981'], 
                ],
            ],
            'labels' => [__('Adultes'), __('Enfants')],
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
