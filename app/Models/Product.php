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
        'child_age_limit',
        'available_days',
        'blackout_dates',
        'cancellation_type',
        'cancellation_specifics',
        'is_lottery',
        'is_on_demand',
        'days_before_opening',
        'custom_field_definitions',
        'supplier_email_subject', // 💡 NOUVEAU CHAMP
        'supplier_email_template'
    ];

    protected $casts = [
        'images' => 'array',
        'child_age_limit' => 'integer',
        'available_days' => 'array',
        'blackout_dates' => 'array',
        'custom_field_definitions' => 'array',
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

    public function productOptions()
    {
        return $this->hasMany(ProductOption::class);
    }
}