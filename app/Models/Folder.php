<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Folder extends Model
{
    // 🎯 ACTIVATION DU TRAIT DE SURVEILLANCE SPATIE
    use LogsActivity;

    protected $fillable = [
        'agency_id',
        'reference',
        'folder_name',
        'lead_traveler_name',
        'hotel_booking_name',
        'contact_phones',
        'pax_adults',
        'pax_children',
        'start_date',
        'end_date',
        'status',
        'folder_fee',
        'total_price',
        'flight_info',
        'first_hotel_check_in',
        'first_hotel_name',
        'first_hotel_address',
        'ticket_dispatch_method',
        'ticket_dispatch_other'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'first_hotel_check_in' => 'date',
        'contact_phones' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($folder) {
            $year = date('Y');
            $latestFolder = static::whereYear('created_at', $year)->latest()->first();
            $nextNumber = $latestFolder ? ((int) substr($latestFolder->reference, -4)) + 1 : 1;
            $folder->reference = 'TAK-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        });
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function folderItems()
    {
        return $this->hasMany(FolderItem::class)->orderBy('service_date', 'asc');
    }

    public function folderPassengers()
    {
        return $this->hasMany(FolderPassenger::class);
    }

    // 🎯 CONFIGURATION DE L'HISTORIQUE
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll() // Enregistre tous les champs modifiés
            ->logOnlyDirty() // N'enregistre que ce qui a VRAIMENT changé
            ->dontSubmitEmptyLogs() // Évite de spammer la base de données si on sauvegarde sans rien modifier
            ->setDescriptionForEvent(fn(string $eventName) => "{$eventName}");
    }
}