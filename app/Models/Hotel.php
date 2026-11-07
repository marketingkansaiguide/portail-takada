<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'google_maps_url',
        'city',
        'alt_names',
    ];

    public function roomTypes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HotelRoomType::class)->orderBy('name');
    }
}