<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section; // CORRECTION DU NAMESPACE ICI
use Filament\Schemas\Schema;
use Carbon\Carbon;

class EditFolder extends EditRecord
{
    protected static string $resource = FolderResource::class;

    /**
     * Variable temporaire pour stocker le récapitulatif textuel des modifications.
     */
    protected ?string $historyMessage = null;

    /**
     * Variable temporaire pour capturer la note saisie par l'utilisateur.
     */
    protected ?string $historyNote = null;

    /**
     * Actions de l'en-tête de la page.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Permet d'injecter dynamiquement un champ de saisie de note en bas du formulaire d'édition.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['history_note'] = null;
        return $data;
    }

    /**
     * Extension du schéma d'édition (Filament v5) pour ajouter la zone de texte de la note d'historique.
     */
    public function form(Schema $schema): Schema
    {
        // On récupère les composants existants du schéma parent (définis dans FolderForm)
        $components = parent::form($schema)->getComponents();
        
        // On y ajoute notre section dédiée à l'historique
        $components[] = Section::make('Suivi de modification')
            ->description('Ajoutez une note contextuelle pour expliquer vos changements dans l\'historique du dossier.')
            ->collapsible()
            ->compact()
            ->schema([
                Textarea::make('history_note')
                    ->label('Note de modification')
                    ->placeholder('Ex: Changement de statut suite au mail de confirmation du client...')
                    ->rows(2)
                    ->maxLength(1000)
                    ->dehydrated(false), // Empêche Filament de sauvegarder cette colonne dans la table SQL
            ]);

        // On retourne le schéma mis à jour
        return $schema->components($components);
    }

    /**
     * Cycle de vie Filament : Capturer l'état avant la sauvegarde définitive en base de données.
     */
    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        $oldData = $record->getOriginal();
        
        // On récupère l'état brut du formulaire pour isoler notre note
        $formState = $this->form->getRawState();
        $this->historyNote = $formState['history_note'] ?? null;

        $newData = $this->form->getState();
        unset($newData['history_note']);

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

        if (!empty($changesText)) {
            $this->historyMessage = "Mise à jour des données du dossier :\n" . implode("\n", $changesText);
        } else {
            $this->historyMessage = null;
        }
    }

    /**
     * Cycle de vie Filament : Exécuté juste après la mise à jour effective en base de données.
     */
    protected function afterSave(): void
    {
        if ($this->historyMessage) {
            $finalSummary = $this->historyMessage;
            
            if (!empty($this->historyNote)) {
                $finalSummary .= "\n\n📝 Note ajoutée :\n" . trim($this->historyNote);
            }

            \App\Models\FolderHistory::create([
                'folder_id' => $this->getRecord()->id,
                'user_id' => auth()->id(),
                'action' => 'Mise à jour',
                'changes_payload' => [
                    'summary' => $finalSummary
                ]
            ]);
        } 
        elseif (!empty($this->historyNote)) {
            \App\Models\FolderHistory::create([
                'folder_id' => $this->getRecord()->id,
                'user_id' => auth()->id(),
                'action' => 'Note',
                'changes_payload' => [
                    'summary' => "📝 Note ajoutée au dossier :\n" . trim($this->historyNote)
                ]
            ]);
        }
    }

    /**
     * Forcer Filament à rediriger l'utilisateur vers cette même page d'édition pour actualiser Livewire.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}