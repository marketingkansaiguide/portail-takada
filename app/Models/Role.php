<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const SUPER_ADMIN = 'super_admin';
    public const AGENCY = 'agency';

    /** Profils verrouillés (nom, accès back-office, suppression). */
    public const LOCKED = [self::SUPER_ADMIN, self::AGENCY];

    // Indique à Eloquent que la clé primaire est une chaîne de caractères non-incrémentale
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'display_name',
        'permissions',
        'is_staff',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_staff' => 'boolean',
    ];
}
