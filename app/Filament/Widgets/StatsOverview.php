<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters; // 💡 Pour lire les filtres
use App\Models\Folder;
use App\Models\FolderItem;
use Carbon\Carbon;

class StatsOverview extends BaseWidget
{
    use InteractsWithPageFilters; // 💡 Indispensable !

    protected static bool $isLazy = false; 
    protected ?string $pollingInterval = null; 
    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        // 1. On récupère les dates choisies par l'utilisateur
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        // 2. Requête Période Actuelle
        $query = Folder::whereIn('status', ['confirmed', 'completed']);
        if ($startDate) $query->whereDate('created_at', '>=', $startDate);
        if ($endDate) $query->whereDate('created_at', '<=', $endDate);
        
        $folders = $query->get();
        $totalCA = $folders->sum('total_price');
        $totalFolders = $folders->count();
        $folderIds = $folders->pluck('id');
        $totalPurchase = FolderItem::whereIn('folder_id', $folderIds)->sum('purchase_total_price');

        $totalMargin = $totalCA - $totalPurchase;
        $marginRate = $totalCA > 0 ? ($totalMargin / $totalCA) * 100 : 0;
        
        $panierMoyenDossier = $totalFolders > 0 ? $totalCA / $totalFolders : 0;
        $margeMoyenneDossier = $totalFolders > 0 ? $totalMargin / $totalFolders : 0;

        // 3. Moteur de Comparaison (si des dates sont sélectionnées)
        $trendCA = 'neutral';
        $diffCA = 0;
        $diffDesc = 'Aucun filtre sélectionné';

        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $days = $start->diffInDays($end) + 1; // Durée de la période

            // Période miroir (ex: si on filtre sur 30 jours, on recule de 30 jours avant la startDate)
            $prevStart = $start->copy()->subDays($days);
            $prevEnd = $end->copy()->subDays($days);

            $prevFolders = Folder::whereIn('status', ['confirmed', 'completed'])
                ->whereDate('created_at', '>=', $prevStart)
                ->whereDate('created_at', '<=', $prevEnd)
                ->get();

            $prevCA = $prevFolders->sum('total_price');

            // Différence en pourcentage
            if ($prevCA > 0) {
                $diffCA = (($totalCA - $prevCA) / $prevCA) * 100;
            } elseif ($totalCA > 0) {
                $diffCA = 100; // Si 0 avant et CA aujourd'hui
            }

            $trendCA = $diffCA > 0 ? 'up' : ($diffCA < 0 ? 'down' : 'neutral');
            
            $sign = $diffCA > 0 ? '+' : '';
            $diffDesc = $sign . number_format($diffCA, 1) . '% vs période préc.';
        }

        // Couleurs et Icones dynamiques
        $caIcon = $trendCA === 'up' ? 'heroicon-m-arrow-trending-up' : ($trendCA === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-globe-americas');
        $caColor = $trendCA === 'up' ? 'success' : ($trendCA === 'down' ? 'danger' : 'primary');

        return [
            Stat::make('CA Généré', number_format($totalCA, 0, '.', ' ') . ' ¥')
                ->description($startDate && $endDate ? $diffDesc : number_format($totalMargin, 0, '.', ' ') . ' ¥ de Marge Brute')
                ->descriptionIcon($caIcon)
                ->color($caColor),

            Stat::make('Taux de Marge Global (%)', number_format($marginRate, 2, '.', '') . ' %')
                ->description('Rentabilité nette moyenne')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($marginRate >= 20 ? 'success' : 'warning'),

            Stat::make('Moyenne / Dossier', number_format($margeMoyenneDossier, 0, '.', ' ') . ' ¥ de Marge')
                ->description('Panier : ' . number_format($panierMoyenDossier, 0, '.', ' ') . ' ¥')
                ->descriptionIcon('heroicon-m-folder-open')
                ->color($margeMoyenneDossier >= 0 ? 'success' : 'danger'),

            Stat::make('Volume de dossiers', $totalFolders . ' dossiers')
                ->description($startDate ? 'Sur la période filtrée' : 'Depuis toujours')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('info'),
        ];
    }
}