<?php

namespace App\Filament\Commercial\Resources\CommercialFolderResource\Pages;

use App\Filament\Commercial\CommercialFolderForm;
use App\Filament\Commercial\Resources\CommercialFolderResource;
use App\Filament\Resources\Folders\FolderResource;
use App\Filament\Resources\Folders\Pages\EditFolder;
use App\Filament\Resources\Folders\PlannedItemsSection;
use App\Models\Folder;
use App\Models\FolderFlightSegment;
use App\Models\FolderHistory;
use App\Models\FolderPassenger;
use App\Models\FolderPlannedItem;
use App\Models\FolderStay;
use App\Support\EntryProfile;
use App\Support\ItemPricer;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Fiche dossier commerciale : un panneau latéral par élément (dossier, voyageur, vol, hôtel, prestation),
 * chacun avec son propre « Enregistrer ». Plus de formulaire global : chaque enregistrement porte sur une donnée
 * d'un seul élément, sous le contrôle de modification concurrente du dossier (lock_version) et tracé dans l'historique.
 * Rien ici n'est visible de la billetterie, des fournisseurs ni de l'administration.
 */
class EditCommercialFolder extends EditFolder
{
    protected static string $resource = CommercialFolderResource::class;

    /** Saisie rapide d'une prestation (prestation, date, pax). */
    public ?array $quickAdd = [];

    public function getTitle(): string
    {
        $folder = $this->getRecord();

        return $folder->folder_name ?: $folder->reference;
    }

    // ───────────────────────── En-tête, formulaire, verrou ─────────────────────────

    protected function getHeaderActions(): array
    {
        // L'ancien bouton « Commander » de l'en-tête est supprimé : la commande se déclenche depuis la barre de sélection
        // de l'onglet Prestations (l'action elle-même reste montable : récapitulatif, verrou, idempotence).
        $kept = array_values(array_filter(
            parent::getHeaderActions(),
            fn ($action) => ! in_array($action->getName(), ['download_pdf', 'delete', 'orderPlannedItems'], true),
        ));

        return [$this->editFolderAction(), ...$kept];
    }

    public function orderPlannedItemsAction(): Action
    {
        return parent::orderPlannedItemsAction();
    }

    /** Aucun bouton d'enregistrement global : chaque panneau enregistre son élément. */
    protected function getFormActions(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(CommercialFolderForm::components());
    }

    protected function usesRowSelection(): bool
    {
        return true;
    }

    /** Les panneaux enregistrent déjà : la commande porte toujours sur des données enregistrées. */
    protected function saveBeforeOrder(): void
    {
    }

    private function folderLock(): int
    {
        return (int) Folder::query()->whereKey($this->getRecord()->getKey())->value('lock_version');
    }

    /** Refuse l'enregistrement si le dossier a changé depuis l'ouverture du panneau. */
    private function lockIsStale(array $data): bool
    {
        $current = (int) Folder::query()->whereKey($this->getRecord()->getKey())->lockForUpdate()->value('lock_version');
        if ((int) ($data['_lock'] ?? -1) === $current) {
            return false;
        }
        $last = FolderHistory::query()->where('folder_id', $this->getRecord()->getKey())->latest('id')->first();
        Notification::make()
            ->title(__('Le dossier a été modifié entre-temps'))
            ->body(__('Dernière modification : :who le :when. Fermez ce panneau et rouvrez-le pour récupérer la version à jour : vos changements n’ont pas été enregistrés.', [
                'who' => $last?->user?->name ?? __('un autre utilisateur'),
                'when' => $last?->created_at?->format('d/m/Y H:i') ?? '—',
            ]))
            ->danger()->persistent()->send();

        return true;
    }

    private function bumpLock(): void
    {
        Folder::query()->whereKey($this->getRecord()->getKey())->increment('lock_version');
        $this->getRecord()->refresh();
    }

    private function log(string $action, string $summary): void
    {
        FolderHistory::logConsolidated($this->getRecord()->getKey(), $action, $summary, 'folder', [], ['event' => 'commercial_panel']);
    }

    /** Ouvre l'élément visé par une alerte : change d'onglet puis monte le panneau. */
    public function openTarget(string $kind, ?int $id = null, ?string $tab = null, ?string $from = null, ?int $nights = null): void
    {
        if ($tab) {
            $this->js('window.tkOpenTarget(' . json_encode($tab) . ')');
        }
        match ($kind) {
            'stay' => $this->mountAction('editStay', ['id' => $id, 'check_in' => $from, 'nights' => $nights]),
            'flight' => $this->mountAction('editFlight', ['id' => $id]),
            'item' => $id ? $this->mountAction('editPlanned', ['id' => $id]) : null,
            default => null,
        };
    }

    // ───────────────────────── Le dossier ─────────────────────────

    public function editFolderAction(): Action
    {
        $fields = ['folder_name', 'quote_reference', 'ops_status', 'sale_type', 'client_agency_id', 'owner_user_id', 'kind', 'start_date', 'end_date',
            'pax_adults', 'pax_children', 'client_contact_name', 'client_contact_email', 'client_contact_phone', 'internal_notes',
            'ticketing_urgent', 'ticketing_urgent_note', 'ticketing_general_notes'];

        return Action::make('editFolder')
            ->label(__('Modifier le dossier'))
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->slideOver()->modalWidth('4xl')
            ->modalHeading(__('Modifier le dossier'))
            ->modalSubmitActionLabel(__('Enregistrer'))
            ->record(fn () => $this->getRecord())
            ->fillForm(fn () => Arr::only($this->getRecord()->fresh()->attributesToArray(), $fields) + ['_lock' => $this->folderLock()])
            ->schema([Hidden::make('_lock'), ...CommercialFolderForm::folderFields()])
            ->action(function (array $data, Action $action) use ($fields) {
                if ($this->lockIsStale($data)) {
                    $action->halt();
                }
                $folder = $this->getRecord();
                $folder->update(Arr::only($data, $fields));
                $this->bumpLock();
                $this->fillForm();
                Notification::make()->title(__('Dossier enregistré'))->success()->send();
            });
    }

    /** Changement de statut depuis la pastille de l'en-tête : un statut ne commande rien. */
    public function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label(__('Statut du dossier'))
            ->modalHeading(__('Changer le statut du dossier'))
            ->modalDescription(__('Changer ce statut ne commande aucune prestation.'))
            ->modalSubmitActionLabel(__('Enregistrer'))
            ->fillForm(fn () => ['ops_status' => $this->getRecord()->ops_status, '_lock' => $this->folderLock()])
            ->schema([Hidden::make('_lock'), Select::make('ops_status')->label(__('Statut'))->options(Folder::OPS_STATUSES)->required()->native(false)])
            ->action(function (array $data, Action $action) {
                if ($this->lockIsStale($data)) {
                    $action->halt();
                }
                $this->getRecord()->update(['ops_status' => $data['ops_status']]);
                $this->bumpLock();
                Notification::make()->title(__('Statut enregistré'))->success()->send();
            });
    }

    public function addNoteAction(): Action
    {
        return Action::make('addNote')
            ->label(__('Ajouter une note de suivi'))
            ->icon('heroicon-o-pencil')->color('gray')
            ->slideOver()
            ->modalSubmitActionLabel(__('Enregistrer'))
            ->schema([Textarea::make('note')->label(__('Note pour l’historique du dossier'))->required()->rows(4)->maxLength(1000)])
            ->action(function (array $data) {
                FolderHistory::logConsolidated($this->getRecord()->getKey(), __('Note'), '📝 ' . __('Note ajoutée au dossier') . " :\n" . trim($data['note']), 'note', [], ['event' => 'manual_note']);
                Notification::make()->title(__('Note ajoutée'))->success()->send();
            });
    }

    // ───────────────────────── Voyageurs ─────────────────────────

    public function editPassengerAction(): Action
    {
        $fields = ['last_name', 'first_name', 'birth_date', 'nationality', 'phone', 'email', 'is_lead', 'dietary_restrictions', 'mobility_concerns', 'special_notes'];

        return Action::make('editPassenger')
            ->label(__('Voyageur'))
            ->slideOver()->modalWidth('3xl')
            ->modalHeading(fn (array $arguments) => ($arguments['id'] ?? null) ? __('Modifier le voyageur') : __('Ajouter un voyageur'))
            ->modalSubmitActionLabel(__('Enregistrer'))
            ->fillForm(function (array $arguments) use ($fields) {
                $passenger = ($arguments['id'] ?? null) ? $this->getRecord()->folderPassengers()->whereKey($arguments['id'])->first() : null;

                return ($passenger ? Arr::only($passenger->attributesToArray(), $fields) : ['is_lead' => false]) + ['_lock' => $this->folderLock()];
            })
            ->schema([Hidden::make('_lock'), ...CommercialFolderForm::passengerFields()])
            ->action(function (array $data, array $arguments, Action $action) use ($fields) {
                if ($this->lockIsStale($data)) {
                    $action->halt();
                }
                $values = Arr::only($data, $fields);
                $passenger = ($arguments['id'] ?? null) ? $this->getRecord()->folderPassengers()->whereKey($arguments['id'])->first() : null;
                $passenger ? $passenger->update($values) : $this->getRecord()->folderPassengers()->create($values);
                $this->bumpLock();
                $this->log($passenger ? __('Voyageur modifié') : __('Voyageur ajouté'), trim(($values['first_name'] ?? '') . ' ' . ($values['last_name'] ?? '')));
            });
    }

    /** Aligne le nombre d'adultes et d'enfants du dossier sur les voyageurs saisis (enfant : moins de 12 ans au départ). */
    public function syncPaxCount(): void
    {
        $passengers = $this->getRecord()->folderPassengers()->get();
        $start = $this->getRecord()->start_date ?? now();
        $children = $passengers->filter(fn ($p) => $p->birth_date && Carbon::parse($p->birth_date)->diffInYears($start) < 12)->count();
        $this->getRecord()->update(['pax_adults' => $passengers->count() - $children, 'pax_children' => $children]);
        $this->bumpLock();
        $this->log(__('Nombre de voyageurs mis à jour'), $passengers->count() . ' ' . __('voyageur(s)'));
        Notification::make()->title(__('Nombre de voyageurs du dossier mis à jour'))->success()->send();
    }

    public function deletePassengerAction(): Action
    {
        return Action::make('deletePassenger')->label(__('Supprimer'))->color('danger')->requiresConfirmation()
            ->modalHeading(__('Supprimer ce voyageur ?'))
            ->action(function (array $arguments) {
                $passenger = $this->getRecord()->folderPassengers()->whereKey($arguments['id'] ?? 0)->first();
                if ($passenger) {
                    $name = trim($passenger->first_name . ' ' . $passenger->last_name);
                    $passenger->delete();
                    $this->bumpLock();
                    $this->log(__('Voyageur supprimé'), $name);
                }
            });
    }

    // ───────────────────────── Vols ─────────────────────────

    public function editFlightAction(): Action
    {
        $fields = ['direction', 'airline', 'flight_number', 'departure_airport', 'departure_local', 'departure_tz', 'arrival_airport', 'arrival_local', 'arrival_tz', 'notes'];

        return Action::make('editFlight')
            ->label(__('Vol'))
            ->slideOver()->modalWidth('3xl')
            ->modalHeading(fn (array $arguments) => ($arguments['id'] ?? null) ? __('Modifier le vol') : __('Ajouter un vol'))
            ->modalSubmitActionLabel(__('Enregistrer'))
            ->fillForm(function (array $arguments) use ($fields) {
                $flight = ($arguments['id'] ?? null) ? $this->getRecord()->flightSegments()->whereKey($arguments['id'])->first() : null;

                return ($flight ? Arr::only($flight->attributesToArray(), $fields) : ['direction' => 'other']) + ['_lock' => $this->folderLock()];
            })
            ->schema([Hidden::make('_lock'), ...CommercialFolderForm::flightFields()])
            ->action(function (array $data, array $arguments, Action $action) use ($fields) {
                if ($this->lockIsStale($data)) {
                    $action->halt();
                }
                $values = Arr::only($data, $fields);
                // Le fuseau se déduit du code aéroport ; il n'est demandé que si le code est inconnu.
                $values['departure_tz'] ??= FolderFlightSegment::timezoneFor((string) ($values['departure_airport'] ?? ''));
                $values['arrival_tz'] ??= FolderFlightSegment::timezoneFor((string) ($values['arrival_airport'] ?? ''));
                $flight = ($arguments['id'] ?? null) ? $this->getRecord()->flightSegments()->whereKey($arguments['id'])->first() : null;
                $flight ? $flight->update($values) : $this->getRecord()->flightSegments()->create($values);
                $this->bumpLock();
                $this->log($flight ? __('Vol modifié') : __('Vol ajouté'), trim(($values['flight_number'] ?? '') . ' ' . ($values['departure_airport'] ?? '') . ' → ' . ($values['arrival_airport'] ?? '')));
            });
    }

    public function deleteFlightAction(): Action
    {
        return Action::make('deleteFlight')->label(__('Supprimer'))->color('danger')->requiresConfirmation()
            ->modalHeading(__('Supprimer ce vol ?'))
            ->action(function (array $arguments) {
                $flight = $this->getRecord()->flightSegments()->whereKey($arguments['id'] ?? 0)->first();
                if ($flight) {
                    $flight->delete();
                    $this->bumpLock();
                    $this->log(__('Vol supprimé'), (string) $flight->flight_number);
                }
            });
    }

    // ───────────────────────── Hôtels ─────────────────────────

    private function stayContext(): array
    {
        return $this->getRecord()->stays()->get()->mapWithKeys(fn (FolderStay $s) => ['record-' . $s->id => [
            'hotel_name' => $s->hotel_name, 'check_in' => $s->check_in?->format('Y-m-d'), 'check_out' => $s->check_out?->format('Y-m-d'), 'room_lines' => $s->room_lines,
        ]])->all();
    }

    public function editStayAction(): Action
    {
        $fields = ['hotel_id', 'hotel_name', 'city', 'address', 'check_in', 'check_out', 'meal_plan', 'room_lines', 'room_category', 'status', 'booked_by',
            'confirmation_ref', 'passenger_ids', 'notes', 'purchase_price', 'sale_price'];

        return Action::make('editStay')
            ->label(__('Hôtel'))
            ->slideOver()->modalWidth('4xl')
            ->modalHeading(fn (array $arguments) => ($arguments['id'] ?? null) ? __('Modifier l’hébergement') : __('Ajouter un hébergement'))
            ->modalSubmitActionLabel(__('Enregistrer'))
            ->fillForm(function (array $arguments) use ($fields) {
                $folder = $this->getRecord();
                $stay = ($arguments['id'] ?? null) ? $folder->stays()->whereKey($arguments['id'])->first() : null;
                if ($stay) {
                    $state = Arr::only($stay->attributesToArray(), $fields);
                    $state['check_in'] = $stay->check_in?->format('Y-m-d');
                    $state['check_out'] = $stay->check_out?->format('Y-m-d');
                    $state['nights'] = $stay->nights();
                } else {
                    // Arrivée proposée : départ du séjour précédent (le plus tardif des départs déjà saisis), ou la plage vide cliquée.
                    $in = $arguments['check_in'] ?? $folder->stays()->max('check_out');
                    $nights = isset($arguments['nights']) ? (int) $arguments['nights'] : null;
                    $state = ['status' => 'to_book', 'booked_by' => 'takada', 'check_in' => $in ? Carbon::parse($in)->format('Y-m-d') : null,
                        'nights' => $nights, 'check_out' => ($in && $nights) ? Carbon::parse($in)->addDays($nights)->format('Y-m-d') : null];
                }

                return $state + ['other_stays' => Arr::except($this->stayContext(), ['record-' . ($arguments['id'] ?? 0)]), '_lock' => $this->folderLock()];
            })
            ->schema([Hidden::make('_lock'), ...CommercialFolderForm::stayFields()])
            ->action(function (array $data, array $arguments, Action $action) use ($fields) {
                if ($this->lockIsStale($data)) {
                    $action->halt();
                }
                $values = Arr::only($data, $fields);
                $stay = ($arguments['id'] ?? null) ? $this->getRecord()->stays()->whereKey($arguments['id'])->first() : null;
                $stay ? $stay->update($values) : $stay = $this->getRecord()->stays()->create($values);
                $this->bumpLock();
                $this->log(($arguments['id'] ?? null) ? __('Hébergement modifié') : __('Hébergement ajouté'), (string) $stay->hotel_name);
            });
    }

    /** Duplique un séjour : mêmes hôtel et chambres, arrivée = départ du séjour copié, même nombre de nuits ; la copie s'ouvre. */
    public function duplicateStay(int $id): void
    {
        $stay = $this->getRecord()->stays()->whereKey($id)->first();
        if (! $stay) {
            return;
        }
        $nights = $stay->nights() ?? 1;
        $copy = $stay->replicate(['confirmation_ref']);
        $copy->check_in = $stay->check_out ?? $stay->check_in;
        $copy->check_out = $copy->check_in?->copy()->addDays($nights);
        $copy->status = 'to_book';
        $copy->save();
        $this->bumpLock();
        $this->log(__('Hébergement ajouté'), $copy->hotel_name . ' (' . __('copie') . ')');
        $this->mountAction('editStay', ['id' => $copy->id]);
    }

    public function deleteStayAction(): Action
    {
        return Action::make('deleteStay')->label(__('Supprimer'))->color('danger')->requiresConfirmation()
            ->modalHeading(__('Supprimer cet hébergement ?'))
            ->action(function (array $arguments) {
                $stay = $this->getRecord()->stays()->whereKey($arguments['id'] ?? 0)->first();
                if ($stay) {
                    $stay->delete();
                    $this->bumpLock();
                    $this->log(__('Hébergement supprimé'), (string) $stay->hotel_name);
                }
            });
    }

    // ───────────────────────── Prestations ─────────────────────────

    private const ITEM_FIELDS = ['product_id', 'service_date', 'quantity', 'pax_adults', 'pax_children', 'pax_teenagers', 'unit_price', 'total_price',
        'purchase_unit_price', 'purchase_total_price', 'custom_values', 'selected_options', 'supplier_id', 'ticketing_notes', 'meeting_point', 'meeting_time',
        'route_from', 'route_to', 'end_time', 'end_date', 'product_option_id', 'title', 'is_internal'];

    public function editPlannedAction(): Action
    {
        return Action::make('editPlanned')
            ->label(__('Prestation'))
            ->slideOver()->modalWidth('4xl')
            ->modalHeading(__('Modifier la prestation'))
            ->modalSubmitActionLabel(__('Enregistrer'))
            ->fillForm(function (array $arguments) {
                $folder = $this->getRecord();
                $planned = $folder->plannedItems()->whereKey($arguments['id'] ?? 0)->firstOrFail();
                $item = Arr::only($planned->attributesToArray(), self::ITEM_FIELDS);
                $item['service_date'] = $planned->service_date?->format('Y-m-d');
                $item['end_date'] = $planned->end_date?->format('Y-m-d');

                // Le contexte du dossier est placé au niveau racine : les champs de la prestation le lisent par `../../`.
                return [
                    'plannedItems' => ['item' => $item],
                    'start_date' => $folder->start_date?->format('Y-m-d'), 'end_date' => $folder->end_date?->format('Y-m-d'),
                    'pax_adults' => $folder->pax_adults, 'pax_children' => $folder->pax_children,
                    'stays' => $folder->stays()->get()->map(fn ($s) => ['hotel_name' => $s->hotel_name, 'city' => $s->city, 'address' => $s->address,
                        'check_in' => $s->check_in?->format('Y-m-d'), 'check_out' => $s->check_out?->format('Y-m-d')])->all(),
                    'flightSegments' => $folder->flightSegments()->get()->map(fn ($f) => ['flight_number' => $f->flight_number, 'arrival_airport' => $f->arrival_airport,
                        'arrival_local' => $f->arrival_local?->format('Y-m-d H:i')])->all(),
                    '_lock' => $this->folderLock(),
                ];
            })
            ->schema([
                Hidden::make('_lock'),
                Group::make()->statePath('plannedItems')->schema([
                    Group::make()->statePath('item')->schema([
                        ...FolderResource::folderItemSchema('commercial'),
                        ...PlannedItemsSection::commercialFields(),
                    ]),
                ]),
            ])
            ->action(function (array $data, array $arguments, Action $action) {
                if ($this->lockIsStale($data)) {
                    $action->halt();
                }
                $planned = $this->getRecord()->plannedItems()->whereKey($arguments['id'] ?? 0)->firstOrFail();
                $planned->update(Arr::only($data['plannedItems']['item'] ?? [], self::ITEM_FIELDS));
                $this->bumpLock();
                $this->selectedPlannedIds = array_values(array_unique($this->selectedPlannedIds));
                Notification::make()->title(__('Prestation enregistrée'))->success()->send();
            });
    }

    public function deletePlannedAction(): Action
    {
        return Action::make('deletePlanned')->label(__('Supprimer'))->color('danger')->requiresConfirmation()
            ->modalHeading(__('Retirer cette prestation de la préparation ?'))
            ->modalDescription(__('Rien n’a été commandé : la ligne préparée est simplement supprimée.'))
            ->action(function (array $arguments) {
                $planned = $this->getRecord()->plannedItems()->whereKey($arguments['id'] ?? 0)->first();
                if ($planned) {
                    $planned->delete();
                    $this->selectedPlannedIds = array_values(array_diff($this->selectedPlannedIds, [(int) $planned->id]));
                    $this->bumpLock();
                }
            });
    }

    // ───────────────────────── Ajout rapide ─────────────────────────

    public function quickAddForm(Schema $schema): Schema
    {
        return $schema->statePath('quickAdd')->columns(['default' => 1, 'md' => 12])->components([
            Select::make('product_id')
                ->label(__('Prestation'))
                ->placeholder(__('Rechercher par nom, ville ou catégorie…'))
                ->searchable()->preload()->allowHtml()->searchDebounce(250)
                ->options(fn () => \App\Support\CatalogueSearch::frequent(auth('web')->id()))
                ->getSearchResultsUsing(fn (string $search): array => \App\Support\CatalogueSearch::search($search))
                ->getOptionLabelUsing(fn ($value): ?string => ($name = \App\Models\Product::whereKey($value)->value('name')) ? e($name) : null)
                ->noSearchResultsMessage(__('Aucune prestation trouvée. Essayez un autre mot-clé.'))
                ->searchPrompt(__('Tapez au moins 2 caractères : nom, ville, catégorie…'))
                ->columnSpan(['md' => 6]),
            DatePicker::make('service_date')->label(__('Date'))->native(false)->displayFormat('d/m/Y')->columnSpan(['md' => 2]),
            TextInput::make('pax')->label(__('Pax'))->numeric()->minValue(1)->columnSpan(['md' => 1]),
        ]);
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        $this->resetQuickAdd();
    }

    private function resetQuickAdd(): void
    {
        $folder = $this->getRecord();
        $lastDate = $folder->plannedItems()->latest('id')->value('service_date');
        $this->quickAdd = [
            'product_id' => null,
            'service_date' => $lastDate ? Carbon::parse($lastDate)->format('Y-m-d') : null,
            'pax' => max(1, (int) $folder->pax_adults + (int) $folder->pax_children),
        ];
        $this->quickAddForm->fill($this->quickAdd);
    }

    /** Crée la ligne préparée avec ses valeurs de base puis ouvre son panneau pour le reste. */
    public function quickAddItem(): void
    {
        $values = $this->quickAddForm->getState();
        $productId = (int) ($values['product_id'] ?? 0);
        if (! $productId || blank($values['service_date'] ?? null)) {
            Notification::make()->title(__('Choisissez une prestation et une date.'))->warning()->send();

            return;
        }
        $product = \App\Models\Product::findOrFail($productId);
        $pax = max(1, (int) ($values['pax'] ?? 1));
        $date = Carbon::parse($values['service_date'])->format('Y-m-d');

        $custom = [];
        $options = [];
        if ($product->product_type === 'transport') {
            $custom['transport_routes'] = [(string) Str::uuid() => ['departure_date' => $date, 'pax_count' => $pax]];
        } elseif (FolderResource::hasSingleOptionGroup($product->id)) {
            $options = [(string) Str::uuid() => ['product_option_id' => null, 'quantity' => 1]];
        }
        $price = ItemPricer::price(['product_id' => $product->id, 'service_date' => $date, 'quantity' => $pax, 'selected_options' => [], 'custom_values' => $custom]);

        $planned = $this->getRecord()->allPlannedItems()->create([
            'product_id' => $product->id, 'service_date' => $date, 'quantity' => $pax, 'pax_adults' => $pax,
            'selected_options' => array_values($options), 'custom_values' => $custom,
            'unit_price' => (int) round($price['unit_price'] ?? 0), 'total_price' => (int) round($price['total_price'] ?? 0),
            'purchase_unit_price' => ($price['purchase_unit_price'] ?? null) !== null ? (int) round($price['purchase_unit_price']) : null,
            'purchase_total_price' => ($price['purchase_total_price'] ?? null) !== null ? (int) round($price['purchase_total_price']) : null,
            'created_by_user_id' => auth('web')->id(),
        ]);
        $this->bumpLock();
        $this->resetQuickAdd();
        $this->mountAction('editPlanned', ['id' => $planned->id]);
    }

    // ───────────────────────── Compatibilité ─────────────────────────

    /** Les anciennes actions du tableau des hôtels (formulaire global) n'existent plus : ouverture directe du panneau. */
    public function addStayOnGap(string $from, int $nights): void
    {
        $this->mountAction('editStay', ['id' => null, 'check_in' => $from, 'nights' => max(1, $nights)]);
    }
}
