<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'fax',
        'payment_type',
        'requires_invoice', // 💡 Nouveau
        'address',
        'commission',
        'notes',
    ];

    protected $casts = [
        'requires_invoice' => 'boolean',
    ];
}