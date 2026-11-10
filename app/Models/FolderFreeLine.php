<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne libre d'un dossier (carnet de voyage, attention client…) : « à fournir », jamais une commande.
 * Aucun fournisseur, aucune mission guide, aucun événement d'agenda, aucune file d'attente : rien ne lit cette table
 * en dehors de l'espace commercial (fiche, synthèse, planning).
 */
class FolderFreeLine extends Model
{
    protected $fillable = ['folder_id', 'label', 'service_date', 'pax', 'sale_price', 'purchase_price', 'note'];

    protected $casts = ['service_date' => 'date', 'pax' => 'integer', 'sale_price' => 'integer', 'purchase_price' => 'integer'];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}
