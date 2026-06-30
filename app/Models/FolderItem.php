<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolderItem extends Model
{
    /**
     * Les attributs qui sont autorisés en écriture de masse (Mass Assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'folder_id',
        'product_id',
        'product_option_id',
        'item_status_id', // 📌 Nouveau champ pour l'interconnexion des statuts dynamiques
        'service_date',
        'quantity',
        'unit_price',
        'total_price',
        'custom_values',
    ];

    /**
     * Les attributs qui doivent être castés (convertis proprement par Laravel).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'service_date' => 'date',
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'custom_values' => 'array', // ⚡ Crucial pour stocker proprement le composant KeyValue de Filament
    ];

    /**
     * Relation : Une prestation appartient à un dossier client spécifique.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Relation : Une prestation fait référence à un produit / activité du catalogue.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relation : Une prestation peut posséder une option spécifique (variante).
     */
    public function productOption(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class);
    }

    /**
     * Relation : Une prestation possède un statut opérationnel personnalisé et dynamique.
     * 🎯 Permet le suivi précis en interne et pour vos agences partenaires.
     */
    public function itemStatus(): BelongsTo
    {
        return $this->belongsTo(ItemStatus::class);
    }
}