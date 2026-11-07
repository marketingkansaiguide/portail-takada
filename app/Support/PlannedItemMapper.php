<?php

namespace App\Support;

use App\Models\FolderPlannedItem;

/**
 * Seul endroit qui décide de ce qui passe d'une prestation préparée à la
 * prestation commandée. Un test de parité compare ces listes aux colonnes
 * réellement présentes en base dans folder_items.
 */
final class PlannedItemMapper
{
    /** Colonnes métier copiées telles quelles. */
    public const COPIED = [
        'product_id', 'product_option_id', 'supplier_id', 'service_date', 'quantity',
        'pax_adults', 'pax_children', 'pax_teenagers',
        'unit_price', 'total_price', 'purchase_unit_price', 'purchase_total_price',
        'custom_values', 'selected_options', 'title', 'is_internal',
    ];

    /** Colonnes de folder_items renseignées par la commande elle-même. */
    public const SET_BY_ORDER = ['folder_id', 'planned_item_id', 'ordered_by_user_id', 'order_batch_id'];

    /** Colonnes purement opérationnelles : jamais issues de la préparation. */
    public const OPERATIONAL = [
        'id', 'item_status_id', 'invoice_received_at', 'google_calendar_event_id',
        'guide_mission_id', 'guide_sync_status', 'guide_sync_error', 'guide_synced_at',
        'calendar_sync_status', 'calendar_sync_error', 'calendar_synced_at',
        'label_exported_at', 'created_at', 'updated_at',
    ];

    /** @return array<string, mixed> */
    public static function toFolderItemAttributes(FolderPlannedItem $planned): array
    {
        $attributes = [];
        foreach (self::COPIED as $column) {
            $value = $planned->getAttribute($column);
            // Valeur absente : on laisse la valeur par défaut de folder_items (comme le parcours
            // B2B) ; un prix d'achat manquant reste signalé par le tableau d'actions existant.
            if ($value !== null) {
                $attributes[$column] = $value;
            }
        }

        return $attributes + ['folder_id' => $planned->folder_id, 'planned_item_id' => $planned->id];
    }

    /** État de formulaire utilisé par le moteur tarifaire (FolderResource::updateItemPrices). */
    public static function toPricingState(FolderPlannedItem $planned): array
    {
        return [
            'product_id' => $planned->product_id,
            'service_date' => $planned->service_date?->format('Y-m-d'),
            'quantity' => $planned->quantity,
            'selected_options' => $planned->selected_options ?? [],
            'custom_values' => $planned->custom_values ?? [],
        ];
    }
}
