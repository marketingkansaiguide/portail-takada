<?php

namespace App\Filament\Commercial\Widgets;

use App\Models\Folder;
use App\Models\FolderItem;
use App\Models\FolderPlannedItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Indicateurs de l'espace commercial (dossiers internes visibles par tous les commerciaux). */
class CommercialStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $userId = auth('web')->id();
        $internal = Folder::query()->internal()->whereNotIn('ops_status', ['termine', 'annule']);

        return [
            Stat::make(__('Mes dossiers en cours'), (clone $internal)->where('owner_user_id', $userId)->count())
                ->description(__('Dossiers internes dont je suis responsable')),
            Stat::make(__('Dossiers internes en cours'), (clone $internal)->count())
                ->description(__('Toute l’équipe')),
            Stat::make(__('Prestations en préparation'), FolderPlannedItem::query()
                ->where('state', FolderPlannedItem::STATE_DRAFT)
                ->whereHas('folder', fn ($q) => $q->internal()->whereNotIn('ops_status', ['termine', 'annule']))
                ->count())
                ->description(__('Pas encore commandées')),
            Stat::make(__('Commandes en attente de confirmation'), FolderItem::query()
                ->whereNotNull('planned_item_id')
                ->whereHas('itemStatus', fn ($q) => $q->where('name', 'En attente de validation'))
                ->count())
                ->description(__('Prestations commandées, fournisseur pas encore confirmé')),
        ];
    }
}
