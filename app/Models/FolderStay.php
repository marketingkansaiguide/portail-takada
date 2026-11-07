<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hébergement d'un dossier (réservé par Takada, l'agence ou le client). Jamais une commande. */
class FolderStay extends Model
{
    public const STATUSES = [
        'to_book' => 'À réserver',
        'pending' => 'En attente',
        'confirmed' => 'Confirmé',
        'booked_by_client' => 'Réservé par le client ou un tiers',
        'cancelled' => 'Annulé',
    ];

    public const BOOKED_BY = ['takada' => 'Takada Travel', 'agency' => 'Agence', 'client' => 'Client'];

    public const ROOM_CATEGORIES = [
        'double' => 'Double', 'twin' => 'Twin', 'single' => 'Single', 'triple' => 'Triple',
        'family' => 'Familiale', 'suite' => 'Suite', 'japanese' => 'Chambre japonaise', 'other' => 'Autre',
    ];

    public const MEAL_PLANS = [
        'none' => 'Sans repas', 'breakfast' => 'Petit-déjeuner', 'half_board' => 'Demi-pension',
        'full_board' => 'Pension complète', 'other' => 'Autre',
    ];

    protected $guarded = [];

    protected $casts = [
        'check_in' => 'date', 'check_out' => 'date', 'passenger_ids' => 'array',
        'rooms' => 'integer', 'purchase_price' => 'integer', 'sale_price' => 'integer',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }

    public function nights(): ?int
    {
        return ($this->check_in && $this->check_out && $this->check_out->gte($this->check_in))
            ? (int) $this->check_in->diffInDays($this->check_out)
            : null;
    }

    /** Un hébergement réservé par le client n'est jamais une prestation à commander par Takada. */
    public function needsTakadaBooking(): bool
    {
        return $this->booked_by === 'takada' && in_array($this->status, ['to_book', 'pending'], true);
    }
}
