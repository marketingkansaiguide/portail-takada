<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // 💡 LA CORRECTION EST ICI : 
    // On autorise Filament à enregistrer TOUS les champs dans la base, sans blocage.
    protected $guarded = [];

    // 💡 RESTAURATION DES CASTS (C'est l'absence de ceci qui faisait planter ton dossier)
    protected $casts = [
        'images' => 'array',
        'child_age_limit' => 'integer',
        'available_days' => 'array',
        'blackout_dates' => 'array',
        'custom_field_definitions' => 'array',
        'is_lottery' => 'boolean',
        'is_on_demand' => 'boolean',
        'is_public' => 'boolean',
        'supplier_fax_header' => 'array',
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

    public function productSuppliers()
    {
        return $this->hasMany(ProductSupplier::class);
    }
}