<?php

namespace App\Filament\Pages;

use App\Models\FolderItem;
use App\Models\ProductOption;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction; 
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
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
     * LOGIQUE INFAILLIBLE : On détecte d'abord les prestations, puis on boucle sur les trajets en PHP.
     */
    public static function getIwaItemsQuery(): Builder
    {
        // 1. On récupère les items avec le statut 1 (sans filtrer par product_type pour éviter les faux négatifs)
        $potentialItems = FolderItem::query()
            ->whereHas('folder', function (Builder $q) {
                $q->whereNotIn('status', ['draft', 'cancelled']);
            })
            ->where('item_status_id', 1)
            ->get();

        $validItemIds = [];

        // 2. On boucle dedans pour valider les fournisseurs de chaque trajet
        foreach ($potentialItems as $item) {
            // Sécurité de décodage du JSON
            $customVals = is_string($item->custom_values) ? json_decode($item->custom_values, true) : ($item->custom_values ?? []);
            $routes = $customVals['transport_routes'] ?? [];

            if (is_array($routes)) {
                foreach ($routes as $route) {
                    $supplierId = $route['supplier_id'] ?? null;
                    
                    // Sécurité : le supplier_id peut être un entier, une string, ou un tableau (si multi-select)
                    $isIwa = is_array($supplierId) ? (in_array(4, $supplierId) || in_array('4', $supplierId)) : ($supplierId == 4);

                    // Dès qu'on trouve au moins UN trajet géré par IWA, on valide la prestation
                    if ($isIwa) {
                        $validItemIds[] = $item->id;
                        break; 
                    }
                }
            }
        }

        // 3. On retourne la requête finale avec uniquement les prestations retenues
        return FolderItem::query()
            ->with(['folder.agency', 'folder.folderPassengers', 'product', 'itemStatus'])
            ->whereIn('id', $validItemIds);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getIwaItemsQuery()
            ->whereNull('label_exported_at') // On compte ceux qui n'ont pas encore été exportés
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
                        if (!is_array($customVals) || empty($customVals['transport_routes'])) {
                            return '<span class="text-gray-500 italic">Aucun trajet</span>';
                        }
                        
                        $routesHtml = [];

                        foreach ($customVals['transport_routes'] as $r) {
                            $supplierId = $r['supplier_id'] ?? null;
                            $isIwa = is_array($supplierId) ? (in_array(4, $supplierId) || in_array('4', $supplierId)) : ($supplierId == 4);
                            
                            // On affiche UNIQUEMENT les trajets IWA
                            if (!$isIwa) continue;

                            $dep = e($r['departure_station'] ?? '?');
                            $arr = e($r['arrival_station'] ?? '?');
                            $date = !empty($r['departure_date']) ? Carbon::parse($r['departure_date'])->format('d/m/Y') : '---';
                            $time = !empty($r['departure_time']) ? ' à ' . e($r['departure_time']) : '';
                            
                            $routesHtml[] = "
                                <div style='margin-bottom: 4px; padding: 2px;'>
                                    <strong style='color: #0f172a;'>{$dep} ➔ {$arr}</strong> <span style='font-size: 0.8rem;'>({$date}{$time})</span>
                                </div>
                            ";
                        }

                        if (empty($routesHtml)) {
                            return '<span class="text-red-500 italic">Aucun trajet IWA trouvé</span>';
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
                    ->default('pending') 
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'pending' => $query->whereNull('label_exported_at'),
                            'exported' => $query->whereNotNull('label_exported_at'),
                            default => $query,
                        };
                    }),

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
                    ->label('Générer fichier Excel IWA')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (Collection $records) {
                        
                        $routesData = collect();

                        foreach ($records as $item) {
                            $customVals = is_string($item->custom_values) ? json_decode($item->custom_values, true) : ($item->custom_values ?? []);
                            $routes = $customVals['transport_routes'] ?? [];
                            
                            $paxName = $item->folder?->lead_traveler_name ?? 'Client';
                            $adults = $item->folder?->pax_adults ?? 1;
                            $children = $item->folder?->pax_children ?? 0;

                            $hasExportedAtLeastOneIwaRoute = false;

                            if (is_array($routes)) {
                                foreach ($routes as $route) {
                                    $supplierId = $route['supplier_id'] ?? null;
                                    $isIwa = is_array($supplierId) ? (in_array(4, $supplierId) || in_array('4', $supplierId)) : ($supplierId == 4);
                                    
                                    if (!$isIwa) continue;

                                    $hasExportedAtLeastOneIwaRoute = true;

                                    $optName = '普通車 (Ordinary)';
                                    if (!empty($route['option_id'])) {
                                        $opt = ProductOption::find($route['option_id']);
                                        if ($opt) $optName = $opt->name;
                                    }

                                    $routesData->push([
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
                                    ]);
                                }
                            }

                            if ($hasExportedAtLeastOneIwaRoute) {
                                $item->label_exported_at = now();
                                $item->save();
                            }
                        }

                        $fileName = 'Commande_IWA_' . now()->format('Y_m_d_His') . '.xlsx';

                        Notification::make()
                            ->title('Fichier Excel généré et statuts mis à jour !')
                            ->success()
                            ->send();

                        return Excel::download(new IwaTicketsExport($routesData), $fileName);
                    }),
            ]);
    }
}