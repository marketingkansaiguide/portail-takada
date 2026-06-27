<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'price_modifier',
        'billing_type'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}