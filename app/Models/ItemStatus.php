<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemStatus extends Model
{
    protected $fillable = ['name', 'color'];

    public function folderItems()
    {
        return $this->hasMany(FolderItem::class);
    }
}