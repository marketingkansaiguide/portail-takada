<?php

namespace App\Observers;

use App\Models\Folder;
use App\Models\FolderHistory;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Notification;

class FolderObserver
{
    /**
     * RÈGLE 1 : Se déclenche quand un NOUVEAU dossier est créé
     */
    public function created(Folder $folder): void
    {
        FolderHistory::logConsolidated(
            $folder->id,
            __('Création du dossier'),
            __('Le dossier :reference a été créé.', ['reference' => $folder->reference]),
            'folder',
            [],
            ['event' => 'created'],
        );

        // On récupère tous les utilisateurs de ton équipe (Admins et Super Admins)
        $admins = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->get();

        Notification::make()
            ->title(__('Nouveau dossier créé'))
            ->body(__('Le dossier :reference vient d\'être ajouté au système.', ['reference' => $folder->reference]))
            ->info() // Couleur bleue
            ->icon('heroicon-o-folder-plus')
            ->sendToDatabase($admins); // Envoie dans la cloche des admins
    }

    /**
     * RÈGLE 2 : Se déclenche quand un dossier EXISTANT est modifié
     */
    public function updated(Folder $folder): void
    {
        $changes = collect($folder->getChanges())
            ->except(['updated_at'])
            ->map(function ($newValue, string $field) use ($folder): array {
                return [
                    'field' => $field,
                    'label' => $this->labelFor($field),
                    'old' => $this->formatValue($field, $folder->getOriginal($field)),
                    'new' => $this->formatValue($field, $newValue),
                ];
            })
            ->filter(fn (array $change): bool => $change['old'] !== $change['new'])
            ->values()
            ->all();

        if ($changes !== []) {
            $summary = __('Données du dossier mises à jour :') . "\n" . collect($changes)
                ->map(fn (array $change): string => "• {$change['label']} : '{$change['old']}' ➔ '{$change['new']}'")
                ->implode("\n");

            FolderHistory::logConsolidated(
                $folder->id,
                __('Mise à jour du dossier'),
                $summary,
                'folder',
                $changes,
                ['event' => 'updated'],
            );
        }

        // 1. On vérifie SI la colonne "status" a été modifiée ET SI sa nouvelle valeur est "confirmed"
        if ($folder->wasChanged('status') && $folder->status === 'confirmed') {
            
            // Qui doit-on prévenir ?
            $notifiables = collect();

            // Si un agent (main_seller) est assigné à ce dossier, on le prévient lui en priorité
            if ($folder->main_seller_id) {
                $notifiables->push($folder->mainSeller);
            } else {
                // Sinon, on prévient tous les admins pour que quelqu'un prenne le relais
                $notifiables = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->get();
            }

            // On envoie la notification
            Notification::make()
                ->title(__('Dossier Confirmé ! 🎉'))
                ->body(__('Le dossier :reference est passé en statut Confirmé. Des actions sont requises !', ['reference' => $folder->reference]))
                ->success() // Couleur verte
                ->icon('heroicon-o-check-circle')
                ->sendToDatabase($notifiables);
        }
        
        // Tu peux rajouter d'autres règles ici ! 
        // Exemple : if ($folder->wasChanged('status') && $folder->status === 'cancelled') { ... }
    }

    private function labelFor(string $field): string
    {
        return __([
            'reference' => 'Référence',
            'folder_name' => 'Nom du dossier',
            'lead_traveler_name' => 'Voyageur principal',
            'hotel_booking_name' => "Nom de réservation d'hôtel",
            'agency_id' => 'Agence',
            'main_seller_id' => 'Vendeur principal',
            'pax_adults' => "Nombre d'adultes",
            'pax_children' => "Nombre d'enfants",
            'ticket_dispatch_method' => "Méthode d'envoi des billets",
            'ticket_dispatch_other' => "Adresse / lieu d'envoi",
            'contact_phones' => 'Téléphones de contact',
            'first_hotel_name' => 'Nom du premier hébergement',
            'first_hotel_check_in' => 'Premier check-in hébergement',
            'first_hotel_address' => 'Adresse du premier hébergement',
            'first_hotel_google_maps_url' => 'Lien Google Maps du premier hébergement',
            'start_date' => 'Date de début',
            'end_date' => 'Date de fin',
            'flight_info' => 'Informations de vol',
            'status' => 'Statut',
            'folder_fee' => 'Frais de dossier',
            'total_price' => 'Prix total',
            'documents' => 'Documents',
            'label_exported_at' => "Date d'export des étiquettes",
        ][$field] ?? str($field)->replace('_', ' ')->headline()->toString());
    }

    private function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('Non renseigné');
        }

        if ($field === 'agency_id') {
            return \App\Models\Agency::find($value)?->name ?? __('Agence supprimée');
        }

        if ($field === 'main_seller_id') {
            return User::find($value)?->name ?? __('Utilisateur supprimé');
        }

        if (in_array($field, ['start_date', 'end_date', 'first_hotel_check_in', 'label_exported_at'], true)) {
            try {
                return Carbon::parse($value)->format($field === 'label_exported_at' ? 'd/m/Y H:i' : 'd/m/Y');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        if (in_array($field, ['folder_fee', 'total_price'], true)) {
            return number_format((float) $value, 0, ',', ' ') . ' ¥';
        }

        if ($field === 'status') {
            return __([
                'draft' => 'Brouillon',
                'pending' => 'En attente de validation',
                'confirmed' => 'Confirmé',
                'completed' => 'Terminé',
                'cancelled' => 'Annulé',
            ][$value] ?? (string) $value);
        }

        if ($field === 'ticket_dispatch_method') {
            return __([
                'hotel' => 'Hôtel',
                'guide' => 'Guide',
                'autre' => 'Autre / Lieu alternatif',
            ][$value] ?? (string) $value);
        }

        if (is_array($value) || is_object($value)) {
            $normalized = json_decode(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);

            return collect($normalized)
                ->map(function ($item, $key): string {
                    if (is_array($item)) {
                        $item = collect($item)->filter(fn ($value) => filled($value))->implode(' · ');
                    }

                    return is_numeric($key) ? (string) $item : "{$key}: {$item}";
                })
                ->filter()
                ->implode(' | ') ?: __('Vide');
        }

        return (string) $value;
    }
}
