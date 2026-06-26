<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $fillable = ['product_period_id', 'min_pax', 'max_pax', 'min_age', 'max_age', 'price'];

    public function productPeriod() {
        return $this->belongsTo(ProductPeriod::class);
    }
}