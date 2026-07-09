<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FolderMessage extends Model
{
    use HasFactory;

    // 💡 AJOUT DE 'attachment_path' ICI POUR AUTORISER LA SAUVEGARDE !
    protected $fillable = [
        'folder_id',
        'user_id',
        'message',
        'attachment_path',
    ];

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}