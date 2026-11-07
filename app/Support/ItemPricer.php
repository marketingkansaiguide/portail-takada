<?php

namespace App\Support;

use App\Filament\Resources\Folders\FolderResource;

/**
 * Appelle le moteur tarifaire unique (FolderResource::updateItemPrices, utilisé par
 * l'admin, le panel agence et la fiche produit) sur un état hors formulaire.
 */
final class ItemPricer
{
    /** @return array{quantity: ?int, unit_price: ?float, total_price: ?float, purchase_unit_price: ?float, purchase_total_price: ?float} */
    public static function price(array $state): array
    {
        $get = function (string $key) use (&$state) {
            return $state[$key] ?? null;
        };
        $set = function (string $key, $value) use (&$state): void {
            $state[$key] = $value;
        };

        FolderResource::updateItemPrices($set, $get);

        $num = fn (string $key) => isset($state[$key]) ? round((float) $state[$key], 4) : null;

        return [
            'quantity' => isset($state['quantity']) ? (int) $state['quantity'] : null,
            'unit_price' => $num('unit_price'),
            'total_price' => $num('total_price'),
            'purchase_unit_price' => $num('purchase_unit_price'),
            'purchase_total_price' => $num('purchase_total_price'),
        ];
    }
}
