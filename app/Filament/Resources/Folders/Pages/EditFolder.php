<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Carbon\Carbon;

class EditFolder extends EditRecord
{
    protected static string $resource = FolderResource::class;

    protected ?string $historyMessage = null;
    protected ?string $historyNote = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label(__('Télécharger Pré-facture'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn ($record) => route('pdf.recapitulatif', $record))
                ->openUrlInNewTab(),
                
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['history_note'] = null;
        return $data;
    }

    public function form(Schema $schema): Schema
    {
        $components = parent::form($schema)->getComponents();
        
        // 💡 NOUVEAU : Création d'une grille à 2 colonnes pour l'affichage côte à côte
        $components[] = \Filament\Forms\Components\Grid::make(2)->schema([
            
            // 1. BLOC SUIVI DE MODIFICATION (À GAUCHE)
            Section::make(__('Suivi de modification'))
                ->description(__('Ajoutez une note contextuelle pour expliquer vos changements dans l\'historique du dossier.'))
                ->columnSpan(1)
                ->collapsible()
                ->compact()
                ->schema([
                    Textarea::make('history_note')
                        ->label(__('Note de modification'))
                        ->placeholder(__('Ex: Changement de statut suite au mail de confirmation du client...'))
                        ->rows(5)
                        ->maxLength(1000)
                        ->dehydrated(false),
                ]),

            // 2. BLOC MESSAGERIE (À DROITE)
            Section::make(__('Messagerie Agence'))
                ->description(__('Échangez directement avec l\'agence (Espace Chat).'))
                ->columnSpan(1)
                ->collapsible()
                ->compact()
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('chat_placeholder')
                        ->hiddenLabel()
                        ->content(new \Illuminate\Support\HtmlString('
                            <div style="background-color: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 0.5rem; padding: 2rem; text-align: center; color: #64748b; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                <svg style="width: 2.5rem; height: 2.5rem; margin-bottom: 0.5rem; color: #94a3b8;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <p style="font-size: 0.875rem; font-weight: 600;">Espace de Chat réservé</p>
                                <p style="font-size: 0.75rem; margin-top: 0.25rem;">L\'interface est prête à être branchée au moteur de messagerie.</p>
                            </div>
                        ')),
                ]),
        ]);

        return $schema->components($components);
    }

    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        $oldData = $record->getOriginal();
        
        $formState = $this->form->getRawState();
        $this->historyNote = $formState['history_note'] ?? null;

        $newData = $this->form->getState();
        unset($newData['history_note']);

        // Recalcul exact du total du dossier pour la BDD et l'historique
        $totalItems = 0;
        if (isset($formState['folderItems']) && is_array($formState['folderItems'])) {
            foreach ($formState['folderItems'] as $item) {
                $totalItems += (float) ($item['total_price'] ?? 0);
            }
        }
        $fee = (float) ($newData['folder_fee'] ?? 0);
        
        $newData['total_price'] = $totalItems + $fee;
        $record->total_price = $newData['total_price'];

        $changesText = [];

        $labels = [
            'agency_id' => __('Agence'),
            'folder_name' => __('Nom du dossier'),
            'lead_traveler_name' => __('Voyageur principal'),
            'hotel_booking_name' => __("Nom de réservation d'hôtel"),
            'contact_phones' => __('Téléphones de contact'),
            'pax_adults' => __("Nombre d'adultes"),
            'pax_children' => __("Nombre d'enfants"),
            'start_date' => __('Date de début'),
            'end_date' => __('Date de fin'),
            'status' => __('Statut'),
            'folder_fee' => __('Frais de dossier'),
            'total_price' => __('Prix total'),
            'flight_info' => __('Informations de vol'),
            'first_hotel_check_in' => __('Premier check-in hôtel'),
            'first_hotel_name' => __('Nom du premier hôtel'),
            'first_hotel_address' => __('Adresse du premier hôtel'),
            'ticket_dispatch_method' => __('Méthode d’envoi des billets'),
            'ticket_dispatch_other' => __('Autre méthode d’envoi'),
        ];

        $statusKeys = [
            'draft' => 'Brouillon',
            'pending' => 'En attente de validation',
            'confirmed' => 'Confirmé / Validé',
            'completed' => 'Voyage terminé',
            'cancelled' => 'Annulé',
        ];

        foreach ($newData as $key => $newValue) {
            if (!array_key_exists($key, $labels)) {
                continue;
            }

            $oldValue = $oldData[$key] ?? null;

            if ($key === 'agency_id') {
                $oldAgency = $oldValue ? (\App\Models\Agency::find($oldValue)?->name ?? __('Inconnue')) : __('Non renseignée');
                $newAgency = $newValue ? (\App\Models\Agency::find($newValue)?->name ?? __('Inconnue')) : __('Vide');
                
                if ($oldAgency !== $newAgency) {
                    $changesText[] = "• " . __('Agence modifiée') . " : '" . $oldAgency . "' ➔ '" . $newAgency . "'";
                }
                continue;
            }

            if ($key === 'status') {
                if ((string)$oldValue !== (string)$newValue) {
                    $oldStatus = __($statusKeys[$oldValue] ?? $oldValue ?? 'Aucun');
                    $newStatus = __($statusKeys[$newValue] ?? $newValue ?? 'Aucun');
                    $changesText[] = "• " . __('Le statut du dossier est passé de') . " '" . $oldStatus . "' " . __('à') . " '" . $newStatus . "'";
                }
                continue;
            }

            if (is_array($oldValue) || is_array($newValue)) {
                if (json_encode($oldValue) !== json_encode($newValue)) {
                    $changesText[] = "• " . __('Les coordonnées') . " '" . $labels[$key] . "' " . __('ont été mises à jour.');
                }
                continue;
            }

            if (($oldValue instanceof \DateTime || $oldValue instanceof Carbon) || ($newValue instanceof \DateTime || $newValue instanceof Carbon) || $key === 'start_date' || $key === 'end_date' || $key === 'first_hotel_check_in') {
                try {
                    $oldString = $oldValue ? Carbon::parse($oldValue)->format('d/m/Y') : __('Non renseignée');
                    $newString = $newValue ? Carbon::parse($newValue)->format('d/m/Y') : __('Vide');
                    
                    if ($oldString !== $newString) {
                        $changesText[] = "• " . $labels[$key] . " " . __('modifié') . " : '" . $oldString . "' ➔ '" . $newString . "'";
                    }
                } catch (\Exception $e) {
                    if ((string)$oldValue !== (string)$newValue) {
                        $changesText[] = "• " . $labels[$key] . " " . __('modifié') . " : '" . ($oldValue ?? __('Vide')) . "' ➔ '" . ($newValue ?? __('Vide')) . "'";
                    }
                }
                continue;
            }

            if ((string)$oldValue !== (string)$newValue) {
                $oldString = $oldValue !== null && $oldValue !== '' ? (string)$oldValue : __('Non renseigné');
                $newString = $newValue !== null && $newValue !== '' ? (string)$newValue : __('Vide');
                $changesText[] = "• " . $labels[$key] . " " . __('modifié') . " : '" . $oldString . "' ➔ '" . $newString . "'";
            }
        }

        if (!empty($changesText)) {
            $this->historyMessage = __('Mise à jour des données du dossier') . " :\n" . implode("\n", $changesText);
        } else {
            $this->historyMessage = null;
        }
    }

    protected function afterSave(): void
    {
        if ($this->historyMessage) {
            $finalSummary = $this->historyMessage;
            
            if (!empty($this->historyNote)) {
                $finalSummary .= "\n\n📝 " . __('Note ajoutée') . " :\n" . trim($this->historyNote);
            }

            \App\Models\FolderHistory::create([
                'folder_id' => $this->getRecord()->id,
                'user_id' => auth()->id(),
                'action' => __('Mise à jour'),
                'changes_payload' => [
                    'summary' => $finalSummary
                ]
            ]);
        } 
        elseif (!empty($this->historyNote)) {
            \App\Models\FolderHistory::create([
                'folder_id' => $this->getRecord()->id,
                'user_id' => auth()->id(),
                'action' => __('Note'),
                'changes_payload' => [
                    'summary' => "📝 " . __('Note ajoutée au dossier') . " :\n" . trim($this->historyNote)
                ]
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}