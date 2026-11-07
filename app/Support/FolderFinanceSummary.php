<?php

namespace App\Support;

use App\Models\Folder;
use App\Models\FolderPlannedItem;

/**
 * Chiffres financiers d'un dossier interne, avec des états honnêtes : un coût non saisi n'est pas « 0 ¥ ».
 * Lecture seule ; les données restent celles du modèle financier existant.
 */
final class FolderFinanceSummary
{
    /**
     * @return array{planned: array, ordered: array, global_sale: ?float}
     */
    public static function for(Folder $folder): array
    {
        $planned = FolderPlannedItem::where('folder_id', $folder->id)->where('state', FolderPlannedItem::STATE_DRAFT)->get();
        $ordered = $folder->billableFolderItems()->get();

        return [
            'planned' => self::block($planned->all()),
            'ordered' => self::block($ordered->all()),
            'global_sale' => $folder->total_price !== null ? (float) $folder->total_price : null,
        ];
    }

    /**
     * @param  array<int, object>  $items
     * @return array{count: int, sale: float, cost: ?float, missing_costs: int, margin: ?float, state: string}
     */
    private static function block(array $items): array
    {
        $sale = 0.0;
        $cost = 0.0;
        $missing = 0;
        foreach ($items as $item) {
            $sale += (float) $item->total_price;
            if ($item->purchase_total_price === null) {
                $missing++;
            } else {
                $cost += (float) $item->purchase_total_price;
            }
        }
        $count = count($items);
        $state = match (true) {
            $count === 0 => 'none',
            $missing === $count => 'unknown',
            $missing > 0 => 'partial',
            default => 'complete',
        };

        return [
            'count' => $count, 'sale' => $sale,
            'cost' => $state === 'unknown' ? null : $cost,
            'missing_costs' => $missing,
            'margin' => $state === 'complete' ? $sale - $cost : null,
            'state' => $state,
        ];
    }
}
