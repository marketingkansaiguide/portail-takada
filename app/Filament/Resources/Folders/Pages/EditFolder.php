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

    /** Prestations préparées cochées dans la liste (espace commercial uniquement). */
    public array $selectedPlannedIds = [];

    protected function usesRowSelection(): bool
    {
        return false;
    }

    public function togglePlannedSelection(int $id): void
    {
        $valid = $this->getRecord()->plannedItems()->whereKey($id)->exists();
        if (! $valid) {
            return;
        }
        $this->selectedPlannedIds = in_array($id, $this->selectedPlannedIds, true)
            ? array_values(array_diff($this->selectedPlannedIds, [$id]))
            : [...$this->selectedPlannedIds, $id];
    }

    public function selectAllPlanned(bool $all): void
    {
        $this->selectedPlannedIds = $all ? $this->getRecord()->plannedItems()->pluck('id')->map(fn ($v) => (int) $v)->all() : [];
    }

    protected function saveBeforeOrder(): void
    {
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
    }

    /** Enregistre le formulaire puis ouvre le récapitulatif de commande des lignes cochées. */
    public function orderSelected(): void
    {
        if ($this->selectedPlannedIds === []) {
            return;
        }
        $this->saveBeforeOrder();
        $this->selectedPlannedIds = array_values(array_intersect(
            $this->selectedPlannedIds,
            $this->getRecord()->plannedItems()->pluck('id')->map(fn ($v) => (int) $v)->all(),
        ));
        if ($this->selectedPlannedIds !== []) {
            $this->mountAction('orderPlannedItems');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->orderPlannedItemsAction(),

            // Réouverture explicite d'un dossier interne annulé ou terminé (motif obligatoire).
            Actions\Action::make('reopenInternalFolder')
                ->label(__('Rouvrir le dossier'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $this->getRecord()->isInternal()
                    && in_array($this->getRecord()->ops_status, ['annule', 'termine'], true)
                    && \App\Support\FolderAccess::staffMay(auth('web')->user(), 'update'))
                ->schema([\Filament\Forms\Components\Textarea::make('reason')->label(__('Motif de la réouverture'))->required()->maxLength(500)])
                ->requiresConfirmation()
                ->action(function (array $data) {
                    $folder = $this->getRecord();
                    $folder->update(['ops_status' => 'en_preparation']);
                    \App\Models\FolderHistory::logConsolidated($folder->id, __('Réouverture du dossier'), __('Dossier rouvert. Motif : :reason', ['reason' => $data['reason']]), 'folder', [], ['event' => 'reopened']);
                    \Filament\Notifications\Notification::make()->title(__('Dossier rouvert'))->success()->send();

                    return redirect(static::getResource()::getUrl('edit', ['record' => $folder]));
                }),

            Actions\Action::make('download_pdf')
                ->label(__('Télécharger Pré-facture'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn ($record) => route('pdf.recapitulatif', $record))
                ->openUrlInNewTab(),
                
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * « Commander les prestations sélectionnées » (dossiers internes) : sélection,
     * récapitulatif recalculé, confirmation explicite, commande tout ou rien.
     */
    protected function orderPlannedItemsAction(): Actions\Action
    {
        $service = app(\App\Services\PlannedItemOrderService::class);

        return Actions\Action::make('orderPlannedItems')
            ->label(__('Commander les prestations sélectionnées'))
            ->icon('heroicon-o-shopping-cart')
            ->color('primary')
            ->visible(fn () => $this->getRecord()->isInternal()
                && \App\Support\FolderAccess::staffMay(auth('web')->user(), 'order'))
            ->disabled(fn () => ! $this->getRecord()->plannedItems()->exists())
            ->modalHeading(__('Commander des prestations préparées'))
            ->modalDescription($this->usesRowSelection()
                ? __('Seules les prestations cochées seront commandées et entreront dans le processus opérationnel (statut « En attente de validation »). Les autres restent en préparation. Vos modifications ont été enregistrées avant ce récapitulatif.')
                : __('Seules les prestations cochées seront commandées et entreront dans le processus opérationnel (statut « En attente de validation »). Les autres restent en préparation. Les modifications non enregistrées du formulaire ne sont pas prises en compte.'))
            ->modalSubmitActionLabel(__('Confirmer la commande'))
            // Espace commercial : pas de confirmation tant qu'une ligne sélectionnée est bloquée (le récapitulatif dit pourquoi).
            ->modalSubmitAction(fn (Actions\Action $action) => ($this->usesRowSelection() && $this->selectionIsBlocking($service)) ? $action->disabled() : $action)
            ->modalWidth('5xl')
            ->fillForm(fn () => [
                'planned_ids' => $this->usesRowSelection() ? $this->selectedPlannedIds : [],
                'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
                'lock_version' => (int) \App\Models\Folder::query()->whereKey($this->getRecord()->getKey())->value('lock_version'),
                'accept_new_prices' => false,
            ])
            ->schema([
                \Filament\Forms\Components\Hidden::make('idempotency_key'),
                \Filament\Forms\Components\Hidden::make('lock_version'),
                $this->usesRowSelection()
                    ? \Filament\Forms\Components\Hidden::make('planned_ids')
                    : \Filament\Forms\Components\CheckboxList::make('planned_ids')
                        ->label(__('Prestations à commander'))
                        ->options(fn () => $this->getRecord()->plannedItems()->with('product')->orderBy('service_date')->orderBy('id')->get()
                            ->mapWithKeys(fn ($item) => [$item->id => $item->label() . ' · ' . (int) $item->quantity . ' pax · ' . number_format((float) $item->total_price, 0, ',', ' ') . ' ¥'])->all())
                        ->required()
                        ->bulkToggleable()
                        ->live(),
                \Filament\Forms\Components\Placeholder::make('order_summary')
                    ->label(__('Récapitulatif'))
                    ->content(fn ($get) => $this->renderOrderSummary($service, (array) $get('planned_ids'))),
                \Filament\Forms\Components\Checkbox::make('accept_new_prices')
                    ->label(__('J’accepte les tarifs actualisés pour les lignes signalées'))
                    ->visible(fn ($get) => $get('planned_ids') && ($service->summary($this->getRecord(), (array) $get('planned_ids'), auth('web')->user())['has_price_changes'] ?? false)),
            ])
            ->action(function (array $data) use ($service) {
                $result = $service->order(
                    $this->getRecord()->fresh(),
                    (array) ($data['planned_ids'] ?? []),
                    auth('web')->user(),
                    (int) ($data['lock_version'] ?? -1),
                    (string) ($data['idempotency_key'] ?? ''),
                    (bool) ($data['accept_new_prices'] ?? false),
                );

                $notification = \Filament\Notifications\Notification::make()->title($result['message']);
                if ($result['status'] === 'completed') {
                    $notification->success()->body(collect($result['lines'] ?? [])->pluck('label')->implode(' · '))->send();

                    return redirect(static::getResource()::getUrl('edit', ['record' => $this->getRecord()]));
                }

                $notification->danger()->persistent()->send();
            });
    }

    private function selectionIsBlocking(\App\Services\PlannedItemOrderService $service): bool
    {
        if ($this->selectedPlannedIds === []) {
            return false;
        }

        return (bool) ($service->summary($this->getRecord(), $this->selectedPlannedIds, auth('web')->user())['has_blocking'] ?? false);
    }

    private function renderOrderSummary(\App\Services\PlannedItemOrderService $service, array $ids): HtmlString
    {
        if ($ids === []) {
            return new HtmlString('<span style="color:#6b7280">' . e(__('Cochez une ou plusieurs prestations.')) . '</span>');
        }
        $summary = $service->summary($this->getRecord(), $ids, auth('web')->user());
        $fmt = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ') . ' ¥';
        $rows = '';
        $totalSale = 0.0;
        $commercial = $this->usesRowSelection();
        $plannedById = $this->usesRowSelection() ? $this->getRecord()->plannedItems()->whereKey($ids)->get()->keyBy('id') : collect();
        foreach ($summary['lines'] as $line) {
            $changed = $line['price_changed'] ? ' style="background:#fffbeb"' : '';
            $sale = $fmt($line['prepared']['total_price']) . ($line['price_changed'] ? ' → <strong>' . $fmt($line['current']['total_price']) . '</strong>' : '');
            $buy = $fmt($line['prepared']['purchase_total_price']) . ($line['price_changed'] ? ' → <strong>' . $fmt($line['current']['purchase_total_price']) . '</strong>' : '');
            $problems = $line['problems'] ? '<div style="color:#b91c1c">⛔ ' . e(implode(', ', $line['problems'])) . (($commercial ?? false) ? ' · <button type="button" style="text-decoration:underline;font-weight:600" wire:click="replaceMountedAction(\'editPlanned\', {id: ' . (int) $line['planned_item_id'] . '})" wire:loading.attr="disabled" wire:target="replaceMountedAction">' . e(__('Compléter')) . '</button><span wire:loading wire:target="replaceMountedAction" role="status" style="margin-left:.5rem;color:#6b7280">⏳ ' . e(__('Ouverture du panneau…')) . '</span>' : '') . '</div>' : '';
            if ($this->usesRowSelection() && ($planned = $plannedById[$line['planned_item_id']] ?? null)) {
                $where = trim(($planned->meeting_time ? $planned->meeting_time . ' · ' : '') . $planned->meeting_point, ' ·');
                $problems .= ($where !== '' ? '<div style="font-size:0.8rem">' . e(__('Rendez-vous')) . ' : ' . e($where) . '</div>' : '')
                    . ($planned->ticketing_notes ? '<div style="font-size:0.8rem;white-space:pre-wrap">📝 ' . e($planned->ticketing_notes) . '</div>' : '');
            }
            $label = $commercial ? trim(preg_replace('/\s*\(\d{2}\/\d{2}\/\d{4}\)\s*$/u', '', (string) $line['label'])) : $line['label'];
            $totalSale += (float) ($line['price_changed'] ? $line['current']['total_price'] : $line['prepared']['total_price']);
            $rows .= '<tr' . $changed . '><td style="padding:4px 8px">' . e($line['service_date'] ?? '—') . '</td><td style="padding:4px 8px">' . e($label) . $problems
                . '<div style="color:#6b7280;font-size:0.8rem">' . e(implode(', ', $line['options'])) . '</div></td><td style="padding:4px 8px;text-align:center">' . (int) $line['pax']
                . '</td><td style="padding:4px 8px;text-align:right">' . $sale . '</td>' . ($commercial ? '' : '<td style="padding:4px 8px;text-align:right">' . $buy . '</td>') . '</tr>';
        }
        $footer = $commercial
            ? '<tfoot><tr style="font-weight:700;border-top:1px solid #d1d5db"><td colspan="3" style="padding:4px 8px;text-align:right">' . e(__('Total')) . '</td><td style="padding:4px 8px;text-align:right">' . $fmt($totalSale) . '</td></tr></tfoot>'
            : '';
        $alerts = '';
        foreach ($summary['folder_problems'] as $problem) {
            $alerts .= '<div style="color:#b91c1c;font-weight:600">⛔ ' . e($problem) . '</div>';
        }
        if ($summary['has_price_changes']) {
            $alerts .= '<div style="color:#b45309;font-weight:600">⚠ ' . e(__('Le tarif a changé depuis la préparation pour les lignes surlignées.')) . '</div>';
        }

        return new HtmlString($alerts . '<table style="width:100%;font-size:0.85rem;margin-top:6px"><thead><tr>'
            . '<th style="text-align:left;padding:4px 8px">' . e(__('Date')) . '</th><th style="text-align:left;padding:4px 8px">' . e(__('Prestation / options')) . '</th>'
            . '<th style="padding:4px 8px">' . e(__('Pax')) . '</th><th style="text-align:right;padding:4px 8px">' . e(__('Vente')) . '</th>' . ($commercial ? '' : '<th style="text-align:right;padding:4px 8px">' . e(__('Achat')) . '</th>')
            . '</tr></thead><tbody>' . $rows . '</tbody>' . $footer . '</table>');
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

        // L'espace commercial n'affiche pas la liste des prestations commandées : son total
        // n'est alors pas recalculé depuis le formulaire (il l'est à la commande).
        if (array_key_exists('folderItems', $formState)) {
            $totalItems = \App\Models\FolderItem::sumBillableFormItems($formState['folderItems'] ?? []);
            $fee = (float) ($newData['folder_fee'] ?? 0);

            $newData['total_price'] = $totalItems + $fee;
            $record->total_price = $newData['total_price'];
        }

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
