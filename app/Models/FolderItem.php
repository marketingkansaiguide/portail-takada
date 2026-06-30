<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Contracts\Activity;

class FolderItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'folder_id', 'product_id', 'product_option_id', 'item_status_id',
        'service_date', 'quantity', 'unit_price', 'total_price', 'custom_values',
    ];

    protected $casts = [
        'service_date' => 'date',
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'custom_values' => 'array',
    ];

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

    public function itemStatus(): BelongsTo
    {
        return $this->belongsTo(ItemStatus::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable() // 🎯 FIX : Enregistre tous les champs (statuts, prix, etc.)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "prestation_{$eventName}");
    }

    public function tapActivity(Activity $activity, string $eventName)
    {
        $activity->subject_id = $this->folder_id;
        $activity->subject_type = Folder::class;
    }
}