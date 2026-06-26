<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'supplier_id',
        'category_id',
        'name',
        'images',
        'description',
        'cancellation_type',
        'cancellation_specifics',
        'is_lottery',
        'is_on_demand',
        'days_before_opening'
    ];

    protected $casts = [
        'images' => 'array',
        'is_lottery' => 'boolean',
        'is_on_demand' => 'boolean',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function productPeriods()
    {
        return $this->hasMany(ProductPeriod::class);
    }
}