<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'supplier_id', 'name', 'type', 'images', 'description', 
        'cancellation_type', 'cancellation_specifics', 
        'is_lottery', 'is_on_demand', 'days_before_opening'
    ];

    // Pour dire que le champ 'images' est un tableau (JSON)
    protected $casts = [
        'images' => 'array',
    ];

    public function supplier() {
        return $this->belongsTo(Supplier::class);
    }

    public function productPeriods() {
        return $this->hasMany(ProductPeriod::class);
    }
}