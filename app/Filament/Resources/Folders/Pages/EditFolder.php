<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;

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
        
        // 💡 CORRECTION DÉFINITIVE : On utilise un Group sur 2 colonnes qui prend toute la largeur
        $components[] = Group::make()
            ->columns(['default' => 1, 'xl' => 2])
            ->columnSpanFull() // Force la prise de 100% de la largeur disponible
            ->schema([
                
                // 1. BLOC SUIVI DE MODIFICATION (À GAUCHE)
                Section::make(__('Suivi de modification'))
                    ->description(__('Ajoutez une note contextuelle pour l\'historique du dossier.'))
                    ->columnSpan(1)
                    ->schema([
                        Textarea::make('history_note')
                            ->hiddenLabel()
                            ->placeholder(__('Ex: Changement de statut suite au mail de confirmation...'))
                            ->rows(17) // Hauteur pour s'aligner avec le composant de chat
                            ->maxLength(1000)
                            ->dehydrated(false),
                    ]),

                // 2. BLOC MESSAGERIE (À DROITE)
                Section::make(__('Messagerie Agence'))
                    ->description(__('Échangez directement avec l\'agence en temps réel.'))
                    ->columnSpan(1)
                    ->schema([
                        Placeholder::make('chat_placeholder')
                            ->hiddenLabel()
                            ->content(fn ($record) => new HtmlString(
                                \Illuminate\Support\Facades\Blade::render('@livewire("folder-chat", ["folder" => $folder])', ['folder' => $record])
                            )),
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