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
    protected static bool $isDiscovered = false;
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
        $totalPurchase = FolderItem::whereIn('folder_id', $folderIds)->notCancelled()->sum('purchase_total_price');

        $totalMargin = $totalCA - $totalPurchase;
        $marginRate = $totalCA > 0 ? ($totalMargin / $totalCA) * 100 : 0;
        
        $panierMoyenDossier = $totalFolders > 0 ? $totalCA / $totalFolders : 0;
        $margeMoyenneDossier = $totalFolders > 0 ? $totalMargin / $totalFolders : 0;

        // 3. Moteur de Comparaison (si des dates sont sélectionnées)
        $trendCA = 'neutral';
        $diffCA = 0;
        $diffDesc = __('Comparaison disponible avec une période complète');

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
            $diffDesc = $sign . number_format($diffCA, 1) . '% ' . __('vs période préc.');
        }

        // Couleurs et Icones dynamiques
        $caIcon = $trendCA === 'up' ? 'heroicon-m-arrow-trending-up' : ($trendCA === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-globe-americas');
        $caColor = $trendCA === 'up' ? 'success' : ($trendCA === 'down' ? 'danger' : 'primary');

        return [
            Stat::make(__('CA Généré'), number_format($totalCA, 0, '.', ' ') . ' ¥')
                ->description($startDate && $endDate ? $diffDesc : __('Tous les dossiers confirmés et terminés'))
                ->descriptionIcon($caIcon)
                ->color($caColor),

            Stat::make(__('Marge brute estimée'), number_format($totalMargin, 0, '.', ' ') . ' ¥')
                ->description(number_format($marginRate, 1, '.', '') . ' % ' . __('du CA'))
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($marginRate >= 20 ? 'success' : 'warning'),

            Stat::make(__('Panier moyen / dossier'), number_format($panierMoyenDossier, 0, '.', ' ') . ' ¥')
                ->description(__('Marge moyenne :') . ' ' . number_format($margeMoyenneDossier, 0, '.', ' ') . ' ¥')
                ->descriptionIcon('heroicon-m-folder-open')
                ->color($panierMoyenDossier > 0 ? 'primary' : 'gray'),

            Stat::make(__('Volume de dossiers'), $totalFolders . ' ' . __('dossiers'))
                ->description($startDate ? __('Sur la période filtrée') : __('Depuis toujours'))
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('info'),
        ];
    }
}
