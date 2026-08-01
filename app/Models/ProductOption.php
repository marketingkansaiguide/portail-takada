<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    protected $fillable = [
        'product_id',
        'group_name',
        'name',
        'code',
        'price_modifier',
        'billing_type',
        'is_required',
    ];

    protected $casts = [
        'price_modifier' => 'integer',
        'is_required' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}