<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSupplier extends Model
{
    protected $fillable = [
        'product_id',
        'supplier_id',
        'email_subject',
        'fax_header',
        'email_template',
    ];

    protected $casts = [
        'fax_header' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}