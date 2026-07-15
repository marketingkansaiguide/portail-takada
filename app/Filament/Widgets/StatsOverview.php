<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Folder;
use App\Models\FolderItem;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // On ne prend en compte que les dossiers générateurs de revenus validés ou terminés
        $folders = Folder::whereIn('status', ['confirmed', 'completed'])->get();
        $totalCA = $folders->sum('total_price');
        $totalFolders = $folders->count();

        $folderIds = $folders->pluck('id');
        $totalPurchase = FolderItem::whereIn('folder_id', $folderIds)->sum('purchase_total_price');

        $totalMargin = $totalCA - $totalPurchase;
        $marginRate = $totalCA > 0 ? ($totalMargin / $totalCA) * 100 : 0;
        $avgMargin = $totalFolders > 0 ? $totalMargin / $totalFolders : 0;

        return [
            Stat::make('Chiffre d\'Affaires Global', number_format($totalCA, 0, '.', ' ') . ' ¥')
                ->description('Sur les dossiers confirmés & terminés')
                ->color('success'),
                
            Stat::make('Marge Brute Globale', number_format($totalMargin, 0, '.', ' ') . ' ¥')
                ->description(number_format($marginRate, 2, '.', '') . '% de rentabilité moyenne')
                ->color($totalMargin >= 0 ? 'success' : 'danger'),
                
            Stat::make('Marge moyenne par dossier', number_format($avgMargin, 0, '.', ' ') . ' ¥')
                ->description("Calculée sur un total de {$totalFolders} dossiers")
                ->color('info'),
        ];
    }
}