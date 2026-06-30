<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FolderPassenger extends Model
{
    protected $table = 'folder_passengers';

    protected $fillable = [
        'folder_id',
        'first_name',
        'last_name',
        'birth_date',
        'nationality',
        'dietary_restrictions',
        'mobility_concerns'
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }
}