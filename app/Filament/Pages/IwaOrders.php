<?php

namespace App\Filament\Pages;

use App\Exports\IwaTicketsExport;
use App\Filament\Resources\Folders\FolderResource;
use App\Models\FolderItem;
use App\Models\ProductOption;
use App\Models\ProductSupplier;
use App\Support\AdminAccess;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group as SchemaGroup;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;

class IwaOrders extends Page implements HasTable
{
    /** Date de début du dossier (sous-requête) : pivot du regroupement, du filtre et des onglets mensuels. */
    public const FOLDER_START_SQL = '(SELECT start_date FROM folders WHERE folders.id = folder_items.folder_id)';

    /** Date de la prestation, à défaut début du dossier. */
    public const ITEM_DATE_SQL = 'COALESCE(folder_items.service_date, (SELECT start_date FROM folders WHERE folders.id = folder_items.folder_id))';

    public static function canAccess(): bool
    {
        return AdminAccess::allows('operations.iwa_orders');
    }

    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';
    protected static ?string $navigationLabel = 'Commandes IWA';
    protected static ?string $title = null;

    public function getTitle(): string
    {
        return __('Commandes de billets de train (IWA)');
    }
    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.train-tickets';

    /**
     * Détermine si un trajet spécifique appartient à IWA (ID = 4) ET est valide (pas vide)
     */
    public static function isIwaRoute(array $route, FolderItem $item): bool
    {
        // 1. On ignore les trajets fantômes/vides
        if (empty($route['departure_station']) && empty($route['arrival_station'])) {
            return false;
        }

        $supplierId = $route['supplier_id'] ?? null;

        // 2. Si le trajet a un fournisseur explicitement défini
        if ($supplierId !== null && $supplierId !== '' && $supplierId !== [] && $supplierId !== '0' && $supplierId !== 0) {
            if (is_array($supplierId)) {
                return in_array(4, $supplierId) || in_array('4', $supplierId);
            }
            return (int) $supplierId === 4;
        }

        // 3. Héritage Prestation : Si la prestation elle-même a un fournisseur explicite
        if (!empty($item->supplier_id)) {
            return $item->currentSupplierId() === 4;
        }

        // 4. Héritage Intra-Prestation : Si un autre trajet de la même prestation est IWA
        $customVals = is_string($item->custom_values) ? json_decode($item->custom_values, true) : ($item->custom_values ?? []);
        $routes = $customVals['transport_routes'] ?? [];
        if (is_array($routes)) {
            foreach ($routes as $r) {
                $sId = $r['supplier_id'] ?? null;
                if ($sId !== null && $sId !== '' && $sId !== [] && $sId !== '0' && $sId !== 0) {
                    $isIwa = is_array($sId) ? (in_array(4, $sId) || in_array('4', $sId)) : ((int)$sId === 4);
                    if ($isIwa) {
                        return true;
                    }
                }
            }
        }

        // 5. Héritage Produit : Si le produit est rattaché à IWA
        if ($item->product_id) {
            $isProductIwa = ProductSupplier::where('product_id', $item->product_id)
                ->where('supplier_id', 4)
                ->exists();
            if ($isProductIwa) {
                return true;
            }
        }

        return true;
    }

    /**
     * Requête pour récupérer uniquement les prestations valides pour IWA
     */
    public static function getIwaItemsQuery(): Builder
    {
        $potentialItems = FolderItem::query()
            ->with(['product', 'itemStatus', 'folder.folderPassengers'])
            // Prestations reprises de l'Excel (import récap) : déjà traitées, jamais dans les commandes IWA.
            ->whereNull('import_ref')
            ->whereHas('folder', function (Builder $q) {
                $q->whereNotIn('status', ['draft', 'cancelled']);
            })
            // 💡 ON BLOQUE LES PRESTATIONS NON-VALIDÉES (Fini les ID 5 et autres intrus)
            ->whereHas('itemStatus', function (Builder $sq) {
                $sq->where('name', 'like', '%ouverture%')
                   ->orWhere('name', 'like', '%Validé%')
                   ->orWhere('name', 'like', '%Confirmé%')
                   ->orWhere('id', 1); // ID 1 : En attente d'ouverture
            })
            ->get();

        $validItemIds = [];

        foreach ($potentialItems as $item) {
            $customVals = is_string($item->custom_values) ? json_decode($item->custom_values, true) : ($item->custom_values ?? []);
            $routes = $customVals['transport_routes'] ?? [];

            // Une prestation sans aucun trajet n'a rien à commander à IWA : jamais dans la liste.
            if (! is_array($routes) || count($routes) === 0) {
                continue;
            }

            foreach ($routes as $route) {
                if (is_array($route) && static::isIwaRoute($route, $item)) {
                    $validItemIds[] = $item->id;
                    break;
                }
            }
        }

        return FolderItem::query()
            ->with(['folder.agency', 'folder.folderPassengers', 'product', 'itemStatus'])
            ->whereIn('id', $validItemIds);
    }

    /** Tri : dossiers par date de début, prestations d'un même dossier ensemble, puis par date. */
    public static function orderByFolderThenDate(Builder $query): Builder
    {
        return $query
            ->orderByRaw(self::FOLDER_START_SQL . ' ASC')
            ->orderBy('folder_items.folder_id')
            ->orderByRaw(self::ITEM_DATE_SQL . ' ASC');
    }

    /** Filtre de période : porte sur la date de DÉBUT DU DOSSIER (un dossier à cheval reste dans son mois de début). */
    public static function filterByFolderStart(Builder $query, ?string $from, ?string $until): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereRaw(self::FOLDER_START_SQL . ' >= ?', [$from]))
            ->when($until, fn (Builder $q) => $q->whereRaw(self::FOLDER_START_SQL . ' <= ?', [$until]));
    }

    /** « référence du dossier — voyageur principal — dates du dossier ». */
    public static function folderGroupLabel(FolderItem $item): string
    {
        $f = $item->folder;
        if (! $f) {
            return '—';
        }
        $dates = ($f->start_date ? Carbon::parse($f->start_date)->format('d/m/Y') : '?')
            . ' → ' . ($f->end_date ? Carbon::parse($f->end_date)->format('d/m/Y') : '?');

        return ($f->folder_name ?: ('Dossier #' . $f->id)) . ' — ' . ($f->lead_traveler_name ?: 'Client') . ' — ' . $dates;
    }

    /** Classe du trajet en japonais (options 23 / 24 / 25 / 26, sinon d'après le libellé de l'option). */
    public static function classLabel(array $route): string
    {
        if (empty($route['option_id'])) {
            return '';
        }
        $byId = [23 => '自由席', 24 => '指定席', 25 => 'グリーン車', 26 => 'グランクラス'];
        if (isset($byId[(int) $route['option_id']])) {
            return $byId[(int) $route['option_id']];
        }
        $opt = ProductOption::find($route['option_id']);
        if (! $opt) {
            return '';
        }

        return match (trim($opt->name)) {
            'Place non réservée - Sans horaire' => '自由席',
            'Place réservée - Standard / éco' => '指定席',
            'Place réservée - Green ou 1ère classe (si existante)' => 'グリーン車',
            'Place réservée - Gran Class' => 'グランクラス',
            default => $opt->name,
        };
    }

    /**
     * Lignes de l'aperçu : un trajet IWA par ligne, groupés par dossier (début de dossier) puis par date.
     *
     * @param  iterable<FolderItem>  $records
     * @return array<int, array<string, mixed>>
     */
    public static function buildPreviewRows(iterable $records): array
    {
        $rows = [];
        foreach ($records as $item) {
            $customVals = is_string($item->custom_values) ? json_decode($item->custom_values, true) : ($item->custom_values ?? []);
            $routes = $customVals['transport_routes'] ?? [];
            if (! is_array($routes)) {
                continue;
            }
            $folder = $item->folder;
            foreach ($routes as $index => $route) {
                if (! is_array($route) || ! static::isIwaRoute($route, $item)) {
                    continue;
                }
                $rows[] = [
                    'item_id' => $item->id,
                    'route_index' => $index,
                    'folder_id' => $item->folder_id,
                    'folder_start' => $folder?->start_date ? Carbon::parse($folder->start_date)->format('Y-m-d') : '',
                    'folder_label' => static::folderGroupLabel($item),
                    'pax_name' => $folder?->lead_traveler_name ?: 'Client',
                    'date' => ! empty($route['departure_date']) ? Carbon::parse($route['departure_date'])->format('Y-m-d') : '',
                    'train_name' => $route['train_number'] ?? '',
                    'departure' => $route['departure_station'] ?? '',
                    'arrival' => $route['arrival_station'] ?? '',
                    'dep_time' => $route['departure_time'] ?? '',
                    'arr_time' => $route['arrival_time'] ?? '',
                    'class' => static::classLabel($route),
                    'specific_requests' => $route['specific_requests'] ?? '',
                    'pax_count' => $route['pax_count'] ?? 1,
                    'children_count' => $route['children_count'] ?? 0,
                    'young_children_count' => $route['young_children_count'] ?? 0,
                    'infants_count' => $route['infants_count'] ?? 0,
                    'fare_ticket_mode' => $route['fare_ticket_mode'] ?? 'same',
                    'fare_ticket_from' => $route['fare_ticket_from'] ?? null,
                    'fare_ticket_to' => $route['fare_ticket_to'] ?? null,
                ];
            }
        }

        $sortKey = fn (array $r) => [$r['folder_start'] === '' ? '9999' : $r['folder_start'], (int) $r['folder_id'], $r['date'], (string) $r['dep_time']];
        usort($rows, fn ($a, $b) => $sortKey($a) <=> $sortKey($b));

        return $rows;
    }

    /** Enregistre dans les trajets du dossier le billet de base choisi dans l'aperçu (les autres clés sont conservées). */
    public static function persistFareTickets(iterable $rows): void
    {
        $byItem = [];
        foreach ($rows as $row) {
            if (! empty($row['item_id']) && isset($row['route_index'])) {
                $byItem[$row['item_id']][$row['route_index']] = $row;
            }
        }

        foreach ($byItem as $itemId => $changes) {
            $item = FolderItem::find($itemId);
            if (! $item) {
                continue;
            }
            $custom = is_array($item->custom_values) ? $item->custom_values : (json_decode((string) $item->custom_values, true) ?: []);
            $routes = $custom['transport_routes'] ?? [];
            foreach ($changes as $index => $row) {
                if (! isset($routes[$index]) || ! is_array($routes[$index])) {
                    continue;
                }
                $mode = $row['fare_ticket_mode'] ?? 'same';
                if (! in_array($mode, ['same', 'custom', 'none'], true)) {
                    $mode = 'same';
                }
                $routes[$index]['fare_ticket_mode'] = $mode;
                if ($mode === 'custom') {
                    $routes[$index]['fare_ticket_from'] = $row['fare_ticket_from'] ?? null;
                    $routes[$index]['fare_ticket_to'] = $row['fare_ticket_to'] ?? null;
                } else {
                    unset($routes[$index]['fare_ticket_from'], $routes[$index]['fare_ticket_to']);
                }
            }
            $custom['transport_routes'] = $routes;
            $item->custom_values = $custom;
            // Information interne de billetterie : pas d'historique ni de notification agence.
            $item->saveQuietly();
        }
    }

    /** @param  array<int, int|string>  $ids */
    public static function markSent(array $ids): int
    {
        return FolderItem::whereIn('id', $ids)->update(['iwa_exported_at' => now()]);
    }

    /** @param  array<int, int|string>  $ids */
    public static function cancelSent(array $ids): int
    {
        return FolderItem::whereIn('id', $ids)->update(['iwa_exported_at' => null]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getIwaItemsQuery()
            ->whereNull('iwa_exported_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::getIwaItemsQuery())
            ->groups([
                TableGroup::make('folder_id')
                    ->label(__('Dossier'))
                    ->getKeyFromRecordUsing(fn (FolderItem $record) => (string) $record->folder_id)
                    ->getTitleFromRecordUsing(fn (FolderItem $record) => static::folderGroupLabel($record))
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query
                        ->orderByRaw(self::FOLDER_START_SQL . ' ' . ($direction === 'desc' ? 'DESC' : 'ASC'))
                        ->orderBy('folder_items.folder_id', $direction))
                    ->scopeQueryByKeyUsing(fn (Builder $query, string $key) => $query->where('folder_items.folder_id', $key))
                    ->collapsible(),
            ])
            ->defaultGroup('folder_id')
            ->defaultSort(fn (Builder $query) => $query->orderByRaw(self::ITEM_DATE_SQL . ' ASC'))
            ->columns([
                Tables\Columns\TextColumn::make('effective_date')
                    ->label(__('Date Globale'))
                    ->getStateUsing(fn (FolderItem $record) => $record->service_date ?? $record->folder?->start_date)
                    ->date('d/m/Y')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(self::ITEM_DATE_SQL . " {$direction}");
                    }),

                Tables\Columns\TextColumn::make('folder.folder_name')
                    ->label(__('Dossier'))
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (FolderItem $record) => $record->folder?->lead_traveler_name . ' (' . ($record->folder?->pax_adults + $record->folder?->pax_children) . ' pax)'),

                Tables\Columns\TextColumn::make('transport_details')
                    ->label(__('Détails des Trajets (IWA)'))
                    ->html()
                    ->getStateUsing(function (FolderItem $record) {
                        $customVals = is_string($record->custom_values) ? json_decode($record->custom_values, true) : ($record->custom_values ?? []);
                        $routes = $customVals['transport_routes'] ?? [];
                        if (!is_array($routes) || empty($routes)) {
                            return '<span class="text-gray-500 italic">Aucun trajet</span>';
                        }

                        $routesHtml = [];

                        foreach ($routes as $r) {
                            if (!static::isIwaRoute($r, $record)) {
                                continue;
                            }

                            $dep = e($r['departure_station'] ?? '?');
                            $arr = e($r['arrival_station'] ?? '?');
                            $date = !empty($r['departure_date']) ? Carbon::parse($r['departure_date'])->format('d/m/Y') : '---';
                            $time = !empty($r['departure_time']) ? ' ' . __('à') . ' ' . e($r['departure_time']) : '';
                            $trainNum = !empty($r['train_number']) ? ' (' . e($r['train_number']) . ')' : '';
                            $requests = filled($r['specific_requests'] ?? null)
                                ? '<div style="white-space: pre-wrap;">' . e(__('Demandes spécifiques/indication complémentaire')) . ' : ' . e($r['specific_requests']) . '</div>'
                                : '';

                            $routesHtml[] = "
                                <div style='margin-bottom: 4px; padding: 4px; background-color: #f8fafc; border-radius: 4px; border: 1px solid #e2e8f0;'>
                                    <strong style='color: #0f172a;'>{$dep} ➔ {$arr}</strong>{$trainNum}<br>
                                    <span style='font-size: 0.8rem; color: #64748b;'>📅 {$date}{$time}</span>
                                    {$requests}
                                </div>
                            ";
                        }

                        if (empty($routesHtml)) {
                            return '<span class="text-gray-400 italic">Aucun trajet IWA valide</span>';
                        }

                        return implode('', $routesHtml);
                    }),

                Tables\Columns\TextColumn::make('itemStatus.name')
                    ->label(__('Statut'))
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                // Statut propre à IWA : indépendant de l'impression des étiquettes (label_exported_at).
                Tables\Columns\TextColumn::make('iwa_exported_at')
                    ->label(__('Statut Export Excel'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('✅ Envoyé à IWA') : __('⏳ En attente'))
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->description(fn (FolderItem $record) => $record->iwa_exported_at ? Carbon::parse($record->iwa_exported_at)->format('d/m/Y') . ' ' . __('à') . ' ' . Carbon::parse($record->iwa_exported_at)->format('H:i') : null)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('iwa_exported')
                    ->label(__('Statut Export'))
                    ->options([
                    'pending' => __('⏳ En attente (Non exporté)'),
                    'exported' => __('✅ Envoyé (Exporté)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'pending' => $query->whereNull('iwa_exported_at'),
                            'exported' => $query->whereNotNull('iwa_exported_at'),
                            default => $query,
                        };
                    })
                    // 💡 CORRECTION DU CRASH SQL 1054 : Empêche Filament de chercher une colonne imaginaire
                    ->attribute('id'),

                Filter::make('service_period')
                    ->form([
                        DatePicker::make('from')->label(__('Dossier commençant après le')),
                        DatePicker::make('until')->label(__('Dossier commençant avant le')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => static::filterByFolderStart($query, $data['from'] ?? null, $data['until'] ?? null)),
            ])
            ->recordActions([
                Action::make('cancel_iwa_sent')
                    ->label(__("Annuler l'envoi IWA"))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (FolderItem $record) => (bool) $record->iwa_exported_at)
                    ->requiresConfirmation()
                    ->action(function (FolderItem $record) {
                        static::cancelSent([$record->id]);
                        Notification::make()->title(__('Envoi IWA annulé'))->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('cancel_iwa_sent_bulk')
                    ->label(__("Annuler l'envoi IWA"))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        static::cancelSent($records->pluck('id')->all());
                        Notification::make()->title(__('Envoi IWA annulé'))->success()->send();
                    }),

                BulkAction::make('export_iwa_excel')
                    ->label(__('Prévisualiser & Générer Excel IWA'))
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->modalHeading(__('Prévisualisation des données IWA'))
                    ->modalDescription(__('Vérifiez et modifiez les données ci-dessous si nécessaire avant de générer le fichier Excel final. Les trajets sont présentés par dossier, puis par date.'))
                    ->modalSubmitActionLabel(__('Générer le fichier Excel'))
                    ->modalWidth('7xl')
                    ->form(function (Collection $records) {
                        return [
                            Repeater::make('excel_rows')
                                ->hiddenLabel()
                                ->schema([
                                    TextInput::make('pax_name')->label(__('Client'))->required()->columnSpan(2),
                                    TextInput::make('date')->label(__('Date'))->columnSpan(2),
                                    TextInput::make('departure')->label(__('Départ'))->columnSpan(2),
                                    TextInput::make('arrival')->label(__('Arrivée'))->columnSpan(2),
                                    TextInput::make('train_name')->label(__('Train'))->columnSpan(2),

                                    TextInput::make('dep_time')->label(__('H. Départ'))->columnSpan(2),
                                    TextInput::make('arr_time')->label(__('H. Arrivée'))->columnSpan(2),
                                    TextInput::make('class')->label(__('Classe'))->columnSpan(2),
                                    Placeholder::make('pax_label')->label(__('Voyageurs'))->columnSpan(4)
                                        ->content(fn (Get $get) => (string) IwaTicketsExport::passengersCell([
                                            'pax_count' => $get('pax_count'), 'children_count' => $get('children_count'),
                                            'young_children_count' => $get('young_children_count'), 'infants_count' => $get('infants_count'),
                                        ])),

                                    SchemaGroup::make(FolderResource::fareTicketFields())->columns(3)->columnSpanFull(),

                                    Hidden::make('item_id'),
                                    Hidden::make('route_index'),
                                    Hidden::make('folder_id'),
                                    Hidden::make('folder_start'),
                                    Hidden::make('folder_label'),
                                    Hidden::make('specific_requests'),
                                    Hidden::make('pax_count'),
                                    Hidden::make('children_count'),
                                    Hidden::make('young_children_count'),
                                    Hidden::make('infants_count'),
                                ])
                                ->columns(10)
                                // Trajets présentés par dossier puis par date : l'en-tête de chaque ligne rappelle le dossier.
                                ->itemLabel(fn (array $state): ?string => ($state['folder_label'] ?? '') . ' · ' . ($state['date'] ?? '') . ' · ' . ($state['departure'] ?? '') . ' → ' . ($state['arrival'] ?? ''))
                                ->default(static::buildPreviewRows($records))
                                ->disableItemCreation()
                                ->reorderable(false),
                        ];
                    })
                    ->action(function (array $data, Collection $records) {
                        $finalRoutesData = collect($data['excel_rows'] ?? []);

                        // Le billet de base choisi dans l'aperçu est enregistré dans le trajet du dossier.
                        static::persistFareTickets($finalRoutesData);

                        $exportedItemIds = $finalRoutesData->pluck('item_id')->filter()->unique()->toArray();
                        if (! empty($exportedItemIds)) {
                            static::markSent($exportedItemIds);
                        }

                        $fileName = 'Commande_IWA_' . now()->format('Y_m_d_His') . '.xlsx';

                        Notification::make()
                            ->title(__('Fichier Excel généré avec succès !'))
                            ->success()
                            ->send();

                        return Excel::download(new IwaTicketsExport($finalRoutesData), $fileName);
                    }),
            ]);
    }
}
