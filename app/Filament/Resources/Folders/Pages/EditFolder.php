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
use Illuminate\Support\HtmlString;

class EditFolder extends EditRecord
{
    protected static string $resource = FolderResource::class;

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
        
        $components[] = Group::make()
            ->columns(['default' => 1, 'xl' => 2])
            ->columnSpanFull() 
            ->schema([
                
                Section::make(__('Suivi de modification'))
                    ->extraAttributes(['data-tk-anchor' => 'suivi'])
                    ->description(__('Ajoutez une note contextuelle pour l\'historique du dossier.'))
                    ->columnSpan(1)
                    ->schema([
                        Textarea::make('history_note')
                            ->hiddenLabel()
                            ->placeholder(__('Ex: Changement de statut suite au mail de confirmation...'))
                            ->rows(17) 
                            ->maxLength(1000)
                            ->dehydrated(false),
                    ]),

                Section::make(__('Messagerie Agence'))
                    // Aucune messagerie agence sur un dossier interne.
                    ->visible(fn ($record) => ! $record?->isInternal())
                    ->extraAttributes(['data-tk-anchor' => 'suivi'])
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

    /** Version du dossier chargée dans ce formulaire (protection contre l'écrasement silencieux). */
    public ?int $loadedLockVersion = null;

    protected function afterFill(): void
    {
        $this->loadedLockVersion = (int) $this->getRecord()->lock_version;
    }

    protected function beforeSave(): void
    {
        // Exécuté dans la transaction d'enregistrement : le verrou de ligne sérialise
        // deux enregistrements simultanés du même dossier.
        $current = (int) \App\Models\Folder::query()->whereKey($this->getRecord()->getKey())->lockForUpdate()->value('lock_version');
        if ($this->loadedLockVersion !== null && $current !== $this->loadedLockVersion) {
            $lastChange = \App\Models\FolderHistory::query()->where('folder_id', $this->getRecord()->getKey())->latest('id')->first();
            \Filament\Notifications\Notification::make()
                ->title(__('Le dossier a été modifié entre-temps'))
                ->body(__('Dernière modification : :who le :when. Rechargez la page pour récupérer la version à jour : vos changements n’ont pas été enregistrés.', [
                    'who' => $lastChange?->user?->name ?? __('un autre utilisateur'),
                    'when' => $lastChange?->created_at?->format('d/m/Y H:i') ?? '—',
                ]))
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }

        $record = $this->getRecord();
        $formState = $this->form->getRawState();
        $this->historyNote = $formState['history_note'] ?? null;

        $newData = $this->form->getState();
        unset($newData['history_note']);

        $totalItems = \App\Models\FolderItem::sumBillableFormItems($formState['folderItems'] ?? []);
        $fee = (float) ($newData['folder_fee'] ?? 0);
        
        $newData['total_price'] = $totalItems + $fee;
        $record->total_price = $newData['total_price'];

    }

    protected function afterSave(): void
    {
        // Toute sauvegarde du formulaire (dossier ou prestations) change la version.
        \App\Models\Folder::query()->whereKey($this->getRecord()->getKey())->increment('lock_version');
        $this->loadedLockVersion = (int) \App\Models\Folder::query()->whereKey($this->getRecord()->getKey())->value('lock_version');

        if (!empty($this->historyNote)) {
            \App\Models\FolderHistory::logConsolidated(
                $this->getRecord()->id,
                __('Note'),
                "📝 " . __('Note ajoutée au dossier') . " :\n" . trim($this->historyNote),
                'note',
                [],
                ['event' => 'manual_note'],
            );
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
