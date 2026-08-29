<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'general_cancellation_policy',
        'admin_email_notifications',
        'technical_alert_emails',
        'chat_reminder_hours',
        'train_ticket_suppliers',
        'maintenance_mode',
        'prevent_search_indexing',
        'catalogue_categories_separate',
        'catalogue_use_carousel',
        'catalogue_products_per_category',
        'site_logo_path',
        'site_favicon_path',
    ];

    protected $casts = [
        'admin_email_notifications' => 'array',
        'technical_alert_emails' => 'array',
        'train_ticket_suppliers' => 'array',
        'maintenance_mode' => 'boolean',
        'prevent_search_indexing' => 'boolean',
        'catalogue_categories_separate' => 'boolean',
        'catalogue_use_carousel' => 'boolean',
        'catalogue_products_per_category' => 'integer',
    ];

    /**
     * Retourne les adresses valides configurées pour les alertes internes.
     *
     * La valeur peut provenir du TagsInput (tableau JSON) ou d'anciennes
     * données enregistrées sous forme de liste séparée par des virgules.
     */
    public function adminNotificationEmails(): array
    {
        return $this->normalizedEmails($this->admin_email_notifications);
    }

    /**
     * Retourne les adresses réservées à la supervision technique.
     */
    public function technicalAlertEmails(): array
    {
        return $this->normalizedEmails($this->technical_alert_emails);
    }

    /** @return array<int, string> */
    private function normalizedEmails(mixed $value): array
    {

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($email): string => trim((string) $email), $value),
            static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }
}
