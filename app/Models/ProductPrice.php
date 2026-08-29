<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $fillable = ['product_period_id', 'min_pax', 'max_pax', 'min_age', 'max_age', 'price', 'purchase_price', 'pricing_mode', 'extra_pax_price'];

    protected $casts = [
        'price' => 'integer',
        'purchase_price' => 'integer',
        'extra_pax_price' => 'integer',
    ];

    public function isGlobalRate(): bool
    {
        return $this->pricing_mode === 'global';
    }

    public function isExtraPaxRate(): bool
    {
        return $this->pricing_mode === 'extra_pax';
    }

    public function productPeriod() {
        return $this->belongsTo(ProductPeriod::class);
    }
}
