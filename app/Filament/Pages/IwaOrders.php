<?php

namespace App\Filament\Pages;

use App\Models\FolderItem;
use App\Models\ProductOption;
use App\Models\ProductSupplier;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\BulkAction;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Exports\IwaTicketsExport;
use Maatwebsite\Excel\Facades\Excel;

class IwaOrders extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';
    protected static ?string $navigationLabel = 'Commandes IWA';
    protected static ?string $title = 'Commandes de billets de train (IWA)';
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
            return (int) $item->supplier_id === 4;
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

            $hasValidIwaRoute = false;

            if (is_array($routes) && count($routes) > 0) {
                foreach ($routes as $route) {
                    if (static::isIwaRoute($route, $item)) {
                        $hasValidIwaRoute = true;
                        break;
                    }
                }
            } else {
                if ((int)$item->supplier_id === 4 || empty($item->supplier_id)) {
                    $hasValidIwaRoute = true;
                }
            }

            if ($hasValidIwaRoute) {
                $validItemIds[] = $item->id;
            }
        }

        return FolderItem::query()
            ->with(['folder.agency', 'folder.folderPassengers', 'product', 'itemStatus'])
            ->whereIn('id', $validItemIds);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getIwaItemsQuery()
            ->whereNull('label_exported_at')
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
            ->query(
                static::getIwaItemsQuery()
                    ->orderByRaw('COALESCE(folder_items.service_date, (SELECT start_date FROM folders WHERE folders.id = folder_items.folder_id)) ASC')
            )
            ->columns([
                Tables\Columns\TextColumn::make('effective_date')
                    ->label('Date Globale')
                    ->getStateUsing(fn (FolderItem $record) => $record->service_date ?? $record->folder?->start_date)
                    ->date('d/m/Y')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("COALESCE(folder_items.service_date, (SELECT start_date FROM folders WHERE folders.id = folder_items.folder_id)) {$direction}");
                    }),

                Tables\Columns\TextColumn::make('folder.folder_name')
                    ->label('Dossier')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (FolderItem $record) => $record->folder?->lead_traveler_name . ' (' . ($record->folder?->pax_adults + $record->folder?->pax_children) . ' pax)'),

                Tables\Columns\TextColumn::make('transport_details')
                    ->label('Détails des Trajets (IWA)')
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
                            $time = !empty($r['departure_time']) ? ' à ' . e($r['departure_time']) : '';
                            $trainNum = !empty($r['train_number']) ? ' (' . e($r['train_number']) . ')' : '';
                            
                            $routesHtml[] = "
                                <div style='margin-bottom: 4px; padding: 4px; background-color: #f8fafc; border-radius: 4px; border: 1px solid #e2e8f0;'>
                                    <strong style='color: #0f172a;'>{$dep} ➔ {$arr}</strong>{$trainNum}<br>
                                    <span style='font-size: 0.8rem; color: #64748b;'>📅 {$date}{$time}</span>
                                </div>
                            ";
                        }

                        if (empty($routesHtml)) {
                            return '<span class="text-gray-400 italic">Aucun trajet IWA valide</span>';
                        }

                        return implode('', $routesHtml);
                    }),

                Tables\Columns\TextColumn::make('itemStatus.name')
                    ->label('Statut')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('label_exported_at')
                    ->label('Statut Export Excel')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? '✅ Envoyé à IWA' : '⏳ En attente')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->description(fn (FolderItem $record) => $record->label_exported_at ? Carbon::parse($record->label_exported_at)->format('d/m/Y à H:i') : null)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('label_exported')
                    ->label('Statut Export')
                    ->options([
                        'pending' => '⏳ En attente (Non exporté)',
                        'exported' => '✅ Envoyé (Exporté)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'pending' => $query->whereNull('label_exported_at'),
                            'exported' => $query->whereNotNull('label_exported_at'),
                            default => $query,
                        };
                    })
                    // 💡 CORRECTION DU CRASH SQL 1054 : Empêche Filament de chercher une colonne `label_exported` imaginaire
                    ->attribute('id'),

                Filter::make('service_period')
                    ->form([
                        DatePicker::make('from')->label('Trajet après le'),
                        DatePicker::make('until')->label('Trajet avant le'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereRaw('COALESCE(folder_items.service_date, (SELECT start_date FROM folders WHERE folders.id = folder_items.folder_id)) >= ?', [$date])
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereRaw('COALESCE(folder_items.service_date, (SELECT start_date FROM folders WHERE folders.id = folder_items.folder_id)) <= ?', [$date])
                            );
                    }),
            ])
            ->bulkActions([
                BulkAction::make('export_iwa_excel')
                    ->label('Prévisualiser & Générer Excel IWA')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->modalHeading('Prévisualisation des données IWA')
                    ->modalDescription('Vérifiez et modifiez les données ci-dessous si nécessaire avant de générer le fichier Excel final.')
                    ->modalSubmitActionLabel('Générer le fichier Excel')
                    ->modalWidth('7xl')
                    ->form(function (Collection $records) {
                        
                        $routesData = [];

                        foreach ($records as $item) {
                            $customVals = is_string($item->custom_values) ? json_decode($item->custom_values, true) : ($item->custom_values ?? []);
                            $routes = $customVals['transport_routes'] ?? [];
                            
                            $paxName = $item->folder?->lead_traveler_name ?? 'Client';
                            $rawAdults = $item->folder?->pax_adults;
                            $adults = ($rawAdults !== null && $rawAdults !== '') ? $rawAdults : 1;
                            $rawChildren = $item->folder?->pax_children;
                            $children = ($rawChildren !== null && $rawChildren !== '') ? $rawChildren : 0;

                            if (is_array($routes)) {
                                foreach ($routes as $route) {
                                    if (!static::isIwaRoute($route, $item)) {
                                        continue;
                                    }

                                    $optName = '普通車 (Ordinary)';
                                    if (!empty($route['option_id'])) {
                                        $opt = ProductOption::find($route['option_id']);
                                        if ($opt) {
                                            // 💡 GESTION ET TRADUCTION DES CLASSES DE SIÈGE
                                            $optName = match (trim($opt->name)) {
                                                'Place non réservée - Sans horaire' => '自由席',
                                                'Place réservée - Standard / éco' => '指定席',
                                                'Place réservée - Green ou 1ère classe (si existante)' => 'グリーン',
                                                'Place réservée - Gran Class' => 'グランクラス',
                                                default => $opt->name,
                                            };
                                        }
                                    }

                                    $routesData[] = [
                                        'item_id' => $item->id,
                                        'pax_name' => $paxName,
                                        'date' => !empty($route['departure_date']) ? Carbon::parse($route['departure_date'])->format('Y-m-d') : '',
                                        'train_name' => $route['train_number'] ?? '',
                                        'departure' => $route['departure_station'] ?? '',
                                        'arrival' => $route['arrival_station'] ?? '',
                                        'dep_time' => $route['departure_time'] ?? '',
                                        'arr_time' => $route['arrival_time'] ?? '',
                                        'class' => $optName,
                                        'pax_adults' => $adults,
                                        'pax_children' => $children,
                                    ];
                                }
                            }
                        }

                        // Tri chronologique
                        usort($routesData, function ($a, $b) {
                            return strcmp($a['date'], $b['date']);
                        });

                        return [
                            Repeater::make('excel_rows')
                                ->hiddenLabel()
                                ->schema([
                                    TextInput::make('pax_name')->label('Client')->required()->columnSpan(2),
                                    TextInput::make('date')->label('Date')->columnSpan(2),
                                    TextInput::make('departure')->label('Départ')->columnSpan(2),
                                    TextInput::make('arrival')->label('Arrivée')->columnSpan(2),
                                    TextInput::make('train_name')->label('Train')->columnSpan(2),
                                    
                                    TextInput::make('dep_time')->label('H. Départ')->columnSpan(2),
                                    TextInput::make('arr_time')->label('H. Arrivée')->columnSpan(2),
                                    TextInput::make('class')->label('Classe')->columnSpan(2),
                                    TextInput::make('pax_adults')->label('Adultes')->numeric()->columnSpan(2),
                                    TextInput::make('pax_children')->label('Enfants')->numeric()->columnSpan(2),
                                    
                                    Hidden::make('item_id'),
                                ])
                                ->columns(10)
                                ->default($routesData)
                                ->disableItemCreation()
                                ->reorderable(false)
                        ];
                    })
                    ->action(function (array $data, Collection $records) {
                        
                        $finalRoutesData = collect($data['excel_rows']);

                        $exportedItemIds = $finalRoutesData->pluck('item_id')->filter()->unique()->toArray();
                        if (!empty($exportedItemIds)) {
                            FolderItem::whereIn('id', $exportedItemIds)->update(['label_exported_at' => now()]);
                        }

                        $fileName = 'Commande_IWA_' . now()->format('Y_m_d_His') . '.xlsx';

                        Notification::make()
                            ->title('Fichier Excel généré avec succès !')
                            ->success()
                            ->send();

                        return Excel::download(new IwaTicketsExport($finalRoutesData), $fileName);
                    }),
            ]);
    }
}