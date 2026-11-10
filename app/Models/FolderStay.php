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
        'rooms' => 'integer', 'room_lines' => 'array', 'purchase_price' => 'integer', 'sale_price' => 'integer',
    ];

    protected static function booted(): void
    {
        // La première ligne de chambres alimente les colonnes historiques ; le total de chambres est la somme.
        static::saving(function (FolderStay $stay): void {
            $lines = collect($stay->room_lines ?? [])
                ->map(fn ($l) => ['room_type_id' => $l['room_type_id'] ?? null, 'label' => filled($l['label'] ?? null) ? trim((string) $l['label']) : null, 'quantity' => max(1, (int) ($l['quantity'] ?? 1))])
                ->filter(fn ($l) => $l['room_type_id'] || $l['label'])
                ->values();
            $stay->room_lines = $lines->isEmpty() ? null : $lines->all();
            if ($lines->isNotEmpty()) {
                $first = $lines->first();
                $stay->rooms = (int) $lines->sum('quantity');
                $stay->room_type_id = $first['room_type_id'];
                $stay->room_label = $first['label'] ?? ($first['room_type_id'] ? HotelRoomType::find($first['room_type_id'])?->name : null);
            }
        });
    }

    /** « 1 × Double + 1 × Twin » ; « 1 ch., catégorie à préciser » quand aucune catégorie n'est saisie. */
    public function roomsSummary(): string
    {
        $lines = $this->room_lines ?: (($this->room_type_id || filled($this->room_label))
            ? [['room_type_id' => $this->room_type_id, 'label' => $this->room_label, 'quantity' => max(1, (int) $this->rooms)]]
            : []);
        if ($lines === []) {
            return max(1, (int) $this->rooms) . ' ' . __('ch.') . ', ' . __('catégorie à préciser');
        }
        $types = HotelRoomType::whereIn('id', collect($lines)->pluck('room_type_id')->filter())->pluck('name', 'id');

        return collect($lines)->map(function ($l) use ($types) {
            $name = $l['label'] ?: ($types[$l['room_type_id'] ?? 0] ?? null);

            return $name ? ((int) ($l['quantity'] ?? 1)) . ' × ' . $name : ((int) ($l['quantity'] ?? 1)) . ' ' . __('ch.') . ', ' . __('catégorie à préciser');
        })->implode(' + ');
    }
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
