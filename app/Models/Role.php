<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    // Indique à Eloquent que la clé primaire est une chaîne de caractères non-incrémentale
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'display_name',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];
}