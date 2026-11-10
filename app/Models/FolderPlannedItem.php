<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prestation préparée d'un dossier interne (lot 3b). Aucun automatisme opérationnel
 * (missions guides, Calendar, tâches, billetterie, statistiques) ne lit cette table.
 * Elle devient une prestation commandée (FolderItem) uniquement via PlannedItemOrderService.
 */
class FolderPlannedItem extends Model
{
    public const STATE_DRAFT = 'draft';
    public const STATE_CONVERTED = 'converted';
    public const STATE_DISCARDED = 'discarded';

    protected $fillable = [
        'folder_id', 'state', 'source', 'source_ref',
        'product_id', 'product_option_id', 'supplier_id', 'service_date', 'quantity',
        'pax_adults', 'pax_children', 'pax_teenagers',
        'unit_price', 'total_price', 'purchase_unit_price', 'purchase_total_price',
        'custom_values', 'selected_options', 'title', 'is_internal',
        'notes', 'ticketing_notes', 'meeting_point', 'meeting_time', 'route_from', 'route_to', 'end_time', 'end_date', 'sale_price_overridden', 'purchase_price_overridden', 'priced_at', 'created_by_user_id', 'updated_by_user_id',
    ];

    protected $casts = [
        'service_date' => 'date', 'end_date' => 'date', 'sale_price_overridden' => 'boolean', 'purchase_price_overridden' => 'boolean',
        'quantity' => 'integer', 'pax_adults' => 'integer', 'pax_children' => 'integer', 'pax_teenagers' => 'integer',
        'unit_price' => 'integer', 'total_price' => 'integer', 'purchase_unit_price' => 'integer', 'purchase_total_price' => 'integer',
        'custom_values' => 'array', 'selected_options' => 'array',
        'is_internal' => 'boolean',
        'priced_at' => 'datetime', 'converted_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (FolderPlannedItem $item): void {
            $folder = Folder::find($item->folder_id);
            if (! $folder || ! $folder->isInternal()) {
                throw new \LogicException('La préparation de prestations est réservée aux dossiers internes.');
            }
            $item->state ??= self::STATE_DRAFT;
            $item->created_by_user_id ??= FolderHistory::resolveActorId();
        });

        static::saving(function (FolderPlannedItem $item): void {
            // Une prestation convertie est figée : elle sert de trace de la commande.
            if ($item->exists && $item->getOriginal('state') === self::STATE_CONVERTED && ! $item->allowConvertedWrite) {
                throw new \LogicException('Une prestation déjà commandée ne se modifie plus depuis la préparation.');
            }
            if ($item->exists && $item->isDirty()) {
                $item->updated_by_user_id = FolderHistory::resolveActorId() ?? $item->updated_by_user_id;
                $item->lock_version = (int) $item->lock_version + 1;
            }
            $item->priced_at = $item->isDirty(['total_price', 'purchase_total_price', 'unit_price']) ? now() : $item->priced_at;
        });

        static::deleting(function (FolderPlannedItem $item): void {
            if ($item->state === self::STATE_CONVERTED) {
                throw new \LogicException('Une prestation déjà commandée ne peut pas être supprimée de la préparation.');
            }
        });

        // Historique sans données personnelles : libellé, date et montants seulement.
        static::created(fn (FolderPlannedItem $item) => $item->logHistory(__('Préparation : prestation ajoutée'), 'created'));
        static::updated(function (FolderPlannedItem $item): void {
            if ($item->wasChanged(['product_id', 'product_option_id', 'service_date', 'quantity', 'total_price', 'purchase_total_price', 'selected_options'])) {
                $item->logHistory(__('Préparation : prestation modifiée'), 'updated');
            }
        });
        static::deleted(fn (FolderPlannedItem $item) => $item->logHistory(__('Préparation : prestation retirée'), 'deleted'));
    }

    /** Autorise l'écriture de conversion par le service de commande uniquement. */
    public bool $allowConvertedWrite = false;

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productOption(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class);
    }

    /**
     * Le formulaire partagé déclare le champ « statut fournisseur » (masqué en préparation) :
     * Filament exige la relation. Une prestation préparée n'a jamais de statut fournisseur.
     */
    public function itemStatus(): BelongsTo
    {
        return $this->belongsTo(ItemStatus::class, 'item_status_id');
    }

    public function folderItem(): BelongsTo
    {
        return $this->belongsTo(FolderItem::class);
    }

    public function label(): string
    {
        $name = $this->title ?: ($this->product?->name ?? __('Prestation'));
        $name = \App\Support\EntryProfile::itemLabel($name, $this->custom_values, $this->route_from, $this->route_to);

        return $this->service_date ? $name . ' (' . $this->service_date->format('d/m/Y') . ')' : $name;
    }

    private function logHistory(string $action, string $event): void
    {
        FolderHistory::logConsolidated(
            $this->folder_id,
            $action,
            $action . ' : ' . $this->label(),
            'service',
            [],
            ['planned_item_id' => $this->id, 'event' => 'planned_' . $event],
        );
    }
}
