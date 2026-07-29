<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    protected $fillable = [
        'agency_id', 'main_seller_id', 'reference', 'folder_name', 'lead_traveler_name',
        'hotel_booking_name', 'contact_phones', 'pax_adults', 'pax_children',
        'start_date', 'end_date', 'status', 'folder_fee', 'total_price',
        'flight_info', 'first_hotel_check_in', 'first_hotel_name',
        'first_hotel_address', 'ticket_dispatch_method', 'ticket_dispatch_other',
        'documents' // 💡 AJOUT INDISPENSABLE ICI
    ];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date',
        'first_hotel_check_in' => 'date', 'contact_phones' => 'array',
        'documents' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($folder) {
            $year = date('Y');
            $latestFolder = static::whereYear('created_at', $year)->latest()->first();
            $nextNumber = $latestFolder ? ((int) substr($latestFolder->reference, -4)) + 1 : 1;
            $folder->reference = 'TAK-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        });

        static::updated(function ($folder) {
            if ($folder->wasChanged(['status', 'folder_name', 'lead_traveler_name'])) {
                $items = $folder->folderItems()->get();
                foreach ($items as $item) {
                    try {
                        $item->syncGoogleCalendar();
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("CALENDAR Erreur cascade dossier -> item : " . $e->getMessage());
                    }
                }
            }
        });
    }

    public function agency() { return $this->belongsTo(Agency::class); }
    public function folderItems() { return $this->hasMany(FolderItem::class)->orderBy('service_date', 'asc'); }
    public function folderPassengers() { return $this->hasMany(FolderPassenger::class); }
    public function mainSeller() { return $this->belongsTo(User::class, 'main_seller_id'); }

    public function activitiesAsSubject()
    {
        return $this->morphMany(\Spatie\Activitylog\Models\Activity::class, 'subject');
    }

    public function histories()
    {
        return $this->hasMany(FolderHistory::class)->latest();
    }
}