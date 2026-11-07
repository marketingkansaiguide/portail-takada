<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    use HasFactory;

    public const ORIGIN_B2B = 'b2b';
    public const ORIGIN_INTERNAL = 'internal';

    protected $fillable = [
        'reference',
        'origin',
        'client_agency_id',
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
            // Aucune origine implicite : un oubli ne doit jamais créer un dossier visible par erreur.
            if (! in_array($folder->origin, [self::ORIGIN_B2B, self::ORIGIN_INTERNAL], true)) {
                throw new \LogicException("Origine du dossier manquante ou invalide : précisez 'b2b' ou 'internal'.");
            }

            if (blank($folder->reference)) {
                do {
                    $reference = 'TKD-' . date('Ym') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
                } while (static::where('reference', $reference)->exists());
                $folder->reference = $reference;
            }

            if (blank($folder->segment)) {
                $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                $folder->segment = $user?->defaultFolderSegment();
            }
        });

        static::saving(fn (Folder $folder) => self::enforceOriginRules($folder));
    }

    /**
     * Garde-fous permanents sur l'origine (vérifiés à chaque enregistrement).
     */
    protected static function enforceOriginRules(Folder $folder): void
    {
        if ($folder->exists && $folder->isDirty('origin')) {
            throw new \LogicException("L'origine d'un dossier ne peut pas être modifiée après sa création.");
        }

        if ($folder->origin === self::ORIGIN_B2B && $folder->agency_id === null) {
            throw new \LogicException("Un dossier agence (B2B) doit être rattaché à son agence propriétaire.");
        }

        if ($folder->origin === self::ORIGIN_INTERNAL && ($folder->agency_id !== null || $folder->main_seller_id !== null)) {
            throw new \LogicException("Un dossier interne ne peut pas avoir d'agence propriétaire ni de vendeur agence. Utilisez l'agence associée (client_agency_id).");
        }
    }

    public function isInternal(): bool
    {
        return $this->origin === self::ORIGIN_INTERNAL;
    }

    public function isB2b(): bool
    {
        return $this->origin === self::ORIGIN_B2B;
    }

    public function scopeB2b($query)
    {
        return $query->where($query->getModel()->getTable() . '.origin', self::ORIGIN_B2B);
    }

    public function scopeInternal($query)
    {
        return $query->where($query->getModel()->getTable() . '.origin', self::ORIGIN_INTERNAL);
    }

    /** Agence associée à titre informatif (statistiques), sans effet sur les accès. */
    public function clientAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'client_agency_id');
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
