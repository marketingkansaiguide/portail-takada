<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'folder_name',
        'lead_traveler_name',
        'hotel_booking_name',
        'agency_id',
        'main_seller_id',
        'pax_adults',
        'pax_children',
        'ticket_dispatch_method',
        'ticket_dispatch_guide_id',
        'ticket_dispatch_guide_name',
        'ticket_dispatch_other',
        'contact_phones',
        'first_hotel_name',
        'first_hotel_check_in',
        'first_hotel_address',
        'first_hotel_google_maps_url',
        'start_date',
        'end_date',
        'flight_info',
        'status',
        'folder_fee',
        'total_price',
        'documents',
        'google_drive_folder_id',
        'segment',
        'label_exported_at',
    ];

    protected $casts = [
        'contact_phones' => 'array',
        'documents' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'first_hotel_check_in' => 'date',
        'label_exported_at' => 'datetime',
        'ticket_dispatch_guide_id' => 'integer',
    ];

    public const SEGMENTS = [
        'fit' => 'FIT',
        'groupe' => 'Groupe',
        'billetterie' => 'Billetterie',
    ];

    protected static function booted(): void
    {
        // Segment fixé automatiquement par le profil du créateur (agence partenaire = billetterie).
        static::creating(function (Folder $folder) {
            if (blank($folder->segment)) {
                $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                $folder->segment = $user?->defaultFolderSegment();
            }
        });
    }

    /** Restreint aux segments autorisés pour l'utilisateur (null = tout, y compris non classés). */
    public function scopeVisibleTo($query, ?User $user)
    {
        $segments = $user?->visibleFolderSegments();

        return $segments === null ? $query : $query->whereIn($query->getModel()->getTable() . '.segment', $segments);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function mainSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'main_seller_id');
    }

    public function folderPassengers(): HasMany
    {
        return $this->hasMany(FolderPassenger::class);
    }

    /**
     * Prestations visibles par les agences et affichées sur les pré-factures (is_internal = false)
     */
    public function folderItems(): HasMany
    {
        return $this->hasMany(FolderItem::class)->where('is_internal', false);
    }

    /** Prestations qui restent facturables après exclusion des annulations. */
    public function billableFolderItems(): HasMany
    {
        return $this->folderItems()->notCancelled();
    }

    public function recalculateBillableTotal(): void
    {
        $this->forceFill([
            'total_price' => (float) $this->billableFolderItems()->sum('total_price') + (float) $this->folder_fee,
        ])->saveQuietly();
    }

    /**
     * Prestations internes confidentielles pour l'équipe Admin (is_internal = true)
     */
    public function internalItems(): HasMany
    {
        return $this->hasMany(FolderItem::class)->where('is_internal', true);
    }

    /**
     * Toutes les prestations confondues
     */
    public function allFolderItems(): HasMany
    {
        return $this->hasMany(FolderItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(FolderHistory::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FolderMessage::class);
    }

    /**
     * Obtenir l'adresse d'envoi effective (Hôtel, Guide ou Autre)
     */
    public function getDispatchAddressAttribute(): string
    {
        if (in_array($this->ticket_dispatch_method, ['guide', 'autre']) && !empty($this->ticket_dispatch_other)) {
            return $this->ticket_dispatch_other;
        }

        return $this->first_hotel_address ?? '';
    }

    /**
     * Obtenir le libellé du mode d'envoi
     */
    public function getDispatchMethodLabelAttribute(): string
    {
        return match ($this->ticket_dispatch_method) {
            'hotel' => 'Hôtel',
            'guide' => 'Guide',
            'autre' => 'Autre / Lieu alternatif',
            default => 'Hôtel',
        };
    }

    /**
     * Obtenir la date de check-in au format japonais (ex: チェックイン日：2026年9月8日)
     */
    public function getFormattedJpCheckinDateAttribute(): string
    {
        $date = $this->first_hotel_check_in ?? $this->start_date;
        if (!$date) return 'チェックイン日：---';

        $cDate = \Carbon\Carbon::parse($date);
        return 'チェックイン日：' . $cDate->format('Y') . '年' . $cDate->format('n') . '月' . $cDate->format('j') . '日';
    }
}
