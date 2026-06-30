<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolderPassenger extends Model
{
    protected $fillable = [
        'folder_id', 'last_name', 'first_name', 'birth_date',
        'nationality', 'dietary_restrictions', 'mobility_concerns',
    ];

    protected $casts = [ 'birth_date' => 'date' ];

    public function folder(): BelongsTo { return $this->belongsTo(Folder::class); }
}