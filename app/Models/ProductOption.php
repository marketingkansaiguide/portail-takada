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
        'adult_price_modifier',
        'child_price_modifier',
        'teen_price_modifier',
        'billing_type',
        'is_required',
    ];

    protected $casts = [
        'price_modifier' => 'integer',
        'adult_price_modifier' => 'integer',
        'child_price_modifier' => 'integer',
        'teen_price_modifier' => 'integer',
        'is_required' => 'boolean',
    ];

    public function priceModifierForAge(bool $isChild): float
    {
        $agePrice = $isChild ? $this->child_price_modifier : $this->adult_price_modifier;

        return $agePrice !== null
            ? (float) $agePrice
            : (float) ($this->price_modifier ?? 0);
    }

    public function priceModifierForAgeGroup(string $group): float
    {
        $agePrice = match ($group) {
            'child' => $this->child_price_modifier,
            'teen' => $this->teen_price_modifier,
            default => $this->adult_price_modifier,
        };

        return $agePrice !== null ? (float) $agePrice : (float) ($this->price_modifier ?? 0);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
