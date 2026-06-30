<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Carbon\Carbon;

class EditFolder extends EditRecord
{
    protected static string $resource = FolderResource::class;

    /**
     * Variable temporaire pour stocker le récapitulatif textuel des modifications.
     */
    protected ?string $historyMessage = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        $oldData = $record->getOriginal();
        $newData = $this->form->getState();

        $changesText = [];

        $labels = [
            'agency_id' => 'Agence',
            'folder_name' => 'Nom du dossier',
            'lead_traveler_name' => 'Voyageur principal',
            'hotel_booking_name' => "Nom de réservation d'hôtel",
            'contact_phones' => 'Téléphones de contact',
            'pax_adults' => "Nombre d'adultes",
            'pax_children' => "Nombre d'enfants",
            'start_date' => 'Date de début',
            'end_date' => 'Date de fin',
            'status' => 'Statut',
            'folder_fee' => 'Frais de dossier',
            'total_price' => 'Prix total',
            'flight_info' => 'Informations de vol',
            'first_hotel_check_in' => 'Premier check-in hôtel',
            'first_hotel_name' => 'Nom du premier hôtel',
            'first_hotel_address' => 'Adresse du premier hôtel',
            'ticket_dispatch_method' => 'Méthode d’envoi des billets',
            'ticket_dispatch_other' => 'Autre méthode d’envoi',
        ];

        foreach ($newData as $key => $newValue) {
            if (!array_key_exists($key, $labels)) {
                continue;
            }

            $oldValue = $oldData[$key] ?? null;

            if ($key === 'agency_id') {
                $oldAgency = $oldValue ? (\App\Models\Agency::find($oldValue)?->name ?? 'Inconnue') : 'Non renseignée';
                $newAgency = $newValue ? (\App\Models\Agency::find($newValue)?->name ?? 'Inconnue') : 'Vide';
                
                if ($oldAgency !== $newAgency) {
                    $changesText[] = "• Agence modifiée : '" . $oldAgency . "' ➔ '" . $newAgency . "'";
                }
                continue;
            }

            if ($key === 'status') {
                if ((string)$oldValue !== (string)$newValue) {
                    $oldStatus = $oldValue ?? 'Aucun';
                    $newStatus = $newValue ?? 'Aucun';
                    $changesText[] = "• Le statut du dossier est passé de '" . $oldStatus . "' à '" . $newStatus . "'";
                }
                continue;
            }

            if (is_array($oldValue) || is_array($newValue)) {
                if (json_encode($oldValue) !== json_encode($newValue)) {
                    $changesText[] = "• Les coordonnées '" . $labels[$key] . "' ont été mises à jour.";
                }
                continue;
            }

            if (($oldValue instanceof \DateTime || $oldValue instanceof Carbon) || ($newValue instanceof \DateTime || $newValue instanceof Carbon) || $key === 'start_date' || $key === 'end_date' || $key === 'first_hotel_check_in') {
                try {
                    $oldString = $oldValue ? Carbon::parse($oldValue)->format('d/m/Y') : 'Non renseignée';
                    $newString = $newValue ? Carbon::parse($newValue)->format('d/m/Y') : 'Vide';
                    
                    if ($oldString !== $newString) {
                        $changesText[] = "• " . $labels[$key] . " modifié : '" . $oldString . "' ➔ '" . $newString . "'";
                    }
                } catch (\Exception $e) {
                    if ((string)$oldValue !== (string)$newValue) {
                        $changesText[] = "• " . $labels[$key] . " modifié : '" . ($oldValue ?? 'Vide') . "' ➔ '" . ($newValue ?? 'Vide') . "'";
                    }
                }
                continue;
            }

            if ((string)$oldValue !== (string)$newValue) {
                $oldString = $oldValue !== null && $oldValue !== '' ? (string)$oldValue : 'Non renseigné';
                $newString = $newValue !== null && $newValue !== '' ? (string)$newValue : 'Vide';
                $changesText[] = "• " . $labels[$key] . " modifié : '" . $oldString . "' ➔ '" . $newString . "'";
            }
        }

        // CORRECTION ICI : Si aucune modification, on force historyMessage à null pour bloquer la sauvegarde
        if (!empty($changesText)) {
            $this->historyMessage = "Mise à jour des données du dossier :\n" . implode("\n", $changesText);
        } else {
            $this->historyMessage = null; 
        }
    }

    protected function afterSave(): void
    {
        // Ne s'exécute que si de réelles modifications ont été détectées
        if ($this->historyMessage) {
            \App\Models\FolderHistory::create([
                'folder_id' => $this->getRecord()->id,
                'user_id' => auth()->id(),
                'action' => 'Mise à jour',
                'changes_payload' => [
                    'summary' => $this->historyMessage
                ]
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}