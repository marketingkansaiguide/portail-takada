<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FolderTask extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function getPriorityAttribute(): string
    {
        return match (true) {
            str_starts_with($this->action_code, 'item_alert_'),
            str_starts_with($this->action_code, 'ic_cards_opening_'),
            str_starts_with($this->action_code, 'invoice_missing_'),
            str_starts_with($this->action_code, 'message_action_required_') => 'critical',

            str_starts_with($this->action_code, 'missing_purchase_price_'),
            str_starts_with($this->action_code, 'booking_open_'),
            str_starts_with($this->action_code, 'unread_msg_'),
            str_starts_with($this->action_code, 'new_folder_confirmed_') => 'attention',

            default => 'normal',
        };
    }

    public function getPriorityRankAttribute(): int
    {
        return match ($this->priority) {
            'critical' => 0,
            'attention' => 1,
            default => 2,
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'critical' => __('Urgent'),
            'attention' => __('À traiter'),
            default => __('À planifier'),
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'critical' => 'danger',
            'attention' => 'warning',
            default => 'primary',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match (true) {
            str_starts_with($this->action_code, 'item_alert_') => __('Problème prestation'),
            str_starts_with($this->action_code, 'ic_cards_opening_') => __('Cartes IC'),
            str_starts_with($this->action_code, 'invoice_missing_') => __('Facture'),
            str_starts_with($this->action_code, 'missing_purchase_price_') => __('Prix d’achat'),
            str_starts_with($this->action_code, 'booking_open_') => __('Réservation ouverte'),
            str_starts_with($this->action_code, 'message_action_required_') => __('Message à traiter'),
            str_starts_with($this->action_code, 'unread_msg_') => __('Message agence'),
            str_starts_with($this->action_code, 'new_folder_confirmed_') => __('Dossier validé'),
            str_starts_with($this->action_code, 'new_folder_') => __('Nouveau dossier'),
            default => __('Prestation à traiter'),
        };
    }
}
