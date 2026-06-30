<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Contracts\Activity;

class FolderPassenger extends Model
{
    use LogsActivity;

    protected $fillable = [
        'folder_id', 'last_name', 'first_name', 'birth_date',
        'nationality', 'dietary_restrictions', 'mobility_concerns',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable() // 🎯 FIX : Enregistre tous les champs des voyageurs
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "voyageur_{$eventName}");
    }

    public function tapActivity(Activity $activity, string $eventName)
    {
        $activity->subject_id = $this->folder_id;
        $activity->subject_type = Folder::class;
    }
}