<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOptionSeasonPrice extends Model
{
    protected $fillable = [
        'product_id',
        'product_option_id',
        'product_period_id',
        'product_price_id',
        'price_modifier',
        'purchase_price_modifier',
        'adult_price_modifier',
        'child_price_modifier',
        'teen_price_modifier',
    ];

    protected $casts = [
        'price_modifier' => 'integer',
        'purchase_price_modifier' => 'integer',
        'adult_price_modifier' => 'integer',
        'child_price_modifier' => 'integer',
        'teen_price_modifier' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $seasonPrice): void {
            if (! $seasonPrice->product_id && $seasonPrice->product_option_id) {
                $seasonPrice->product_id = ProductOption::query()
                    ->whereKey($seasonPrice->product_option_id)
                    ->value('product_id');
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productOption()
    {
        return $this->belongsTo(ProductOption::class);
    }

    public function productPeriod()
    {
        return $this->belongsTo(ProductPeriod::class);
    }

    public function productPrice()
    {
        return $this->belongsTo(ProductPrice::class);
    }
}
