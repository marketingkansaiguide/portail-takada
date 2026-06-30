<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FolderHistory extends Model
{
    protected $guarded = [];
    protected $casts = ['changes_payload' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function folder() { return $this->belongsTo(Folder::class); }
}