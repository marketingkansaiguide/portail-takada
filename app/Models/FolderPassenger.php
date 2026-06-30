<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity; // 🎯 IMPORT V5
use Spatie\Activitylog\Support\LogOptions; // 🎯 IMPORT V5
use Spatie\Activitylog\Contracts\Activity; // 🎯 IMPORT V5

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
            ->logOnly(['last_name', 'first_name', 'birth_date', 'nationality', 'dietary_restrictions', 'mobility_concerns'])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->dontLogEmptyChanges() // 🎯 FONCTION V5 CORRECTE
            ->setDescriptionForEvent(fn(string $eventName) => "voyageur_{$eventName}");
    }

    public function tapActivity(Activity $activity, string $eventName)
    {
        if ($this->folder_id) {
            $activity->subject_id = $this->folder_id;
            $activity->subject_type = Folder::class;
        }
    }
}