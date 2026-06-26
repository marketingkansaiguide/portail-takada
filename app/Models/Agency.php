<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_group_id',
        'name',
        'contact_name',
        'email',
        'phone',
        'address',
        'is_approved',
    ];

    // On explique à Laravel que "Cette agence appartient à un Groupe Client"
    public function clientGroup()
    {
        return $this->belongsTo(ClientGroup::class);
    }
}