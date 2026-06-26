<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPeriod extends Model
{
    protected $fillable = ['product_id', 'name', 'start_date', 'end_date'];

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function productPrices() {
        return $this->hasMany(ProductPrice::class);
    }
}