<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Trace d'une commande de prestations préparées (qui, quand, quelles lignes, quels prix). */
class FolderOrderBatch extends Model
{
    protected $fillable = [
        'folder_id', 'user_id', 'idempotency_key', 'selection_hash', 'planned_item_ids',
        'status', 'result', 'snapshot', 'price_changes_acknowledged',
    ];

    protected $casts = [
        'planned_item_ids' => 'array',
        'result' => 'array',
        'snapshot' => 'array',
        'price_changes_acknowledged' => 'boolean',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FolderItem::class, 'order_batch_id');
    }
}
