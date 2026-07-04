<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FolderMessage extends Model
{
    protected $fillable = ['folder_id', 'user_id', 'message'];

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}