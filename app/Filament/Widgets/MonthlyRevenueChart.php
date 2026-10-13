<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\Folder;
use Carbon\Carbon;

class MonthlyRevenueChart extends ChartWidget
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
        return ($this->filters['startDate'] ?? null) || ($this->filters['endDate'] ?? null)
            ? __('Évolution du CA — période sélectionnée')
            : __('Évolution du CA — 12 derniers mois');
    }
    protected int | string | array $columnSpan = 1;

    // 💡 On impose la même hauteur réduite que le camembert
    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $data = [];
        $labels = [];
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $firstMonth = $startDate
            ? Carbon::parse($startDate)->startOfMonth()
            : ($endDate ? Carbon::parse($endDate)->startOfMonth()->subMonths(11) : Carbon::now()->startOfMonth()->subMonths(11));
        $lastMonth = $endDate ? Carbon::parse($endDate)->startOfMonth() : Carbon::now()->startOfMonth();

        $monthlyTotals = Folder::query()
            ->whereIn('status', ['confirmed', 'completed'])
            ->when($startDate, fn ($query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->whereDate('created_at', '<=', $endDate))
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COALESCE(SUM(total_price), 0) as total')
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->get()
            ->keyBy(fn ($row) => sprintf('%04d-%02d', $row->year, $row->month));

        for ($date = $firstMonth->copy(); $date->lte($lastMonth); $date->addMonth()) {
            $labels[] = ucfirst($date->translatedFormat('M Y')); 
            $data[] = (float) ($monthlyTotals->get($date->format('Y-m'))?->total ?? 0);
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
