<?php

namespace App\Filament\Pages;

use App\Models\Folder;
use App\Models\FolderItem;
use App\Models\ItemStatus;
use App\Models\ProductOption;
use BackedEnum;
use UnitEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

class IcCards extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Cartes IC';
    protected static ?string $title = 'Gestion & Suivi des Cartes IC à venir';
    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.ic-cards';

    /**
     * Reconstitution de la requête pour cibler le produit Carte IC (ID: 3) dans les dossiers actifs
     */
    public static function getIcItemsQuery(): Builder
    {
        return FolderItem::query()
            ->with([
                'folder.agency',
                'folder.mainSeller',
                'product.category',
                'itemStatus',
                'supplier',
            ])
            // 💡 Filtre strict : Dossiers confirmés uniquement (pas brouillon, pas terminé, pas annulé)
            ->whereHas('folder', function (Builder $q) {
                $q->whereNotIn('status', ['draft', 'completed', 'cancelled']);
            })
            // 💡 Ciblage propre et direct par l'ID du produit Carte IC (ID: 3)
            ->where('product_id', 3);
    }

    /**
     * Calcul synthétique de toutes les cartes IC "En attente de validation"
     */
    #[Computed]
    public function icCardsSummary(): array
    {
        $pendingItems = static::getIcItemsQuery()
            ->whereHas('itemStatus', function ($q) {
                $q->where('name', 'En attente de validation');
            })
            ->get();

        $summary = [];
        $options = ProductOption::where('product_id', 3)->get();

        foreach ($options as $option) {
            $summary[$option->id] = [
                'id' => $option->id,
                'name' => $option->name,
                'count' => 0,
            ];
        }

        $noOptionCount = 0;

        foreach ($pendingItems as $item) {
            $hasOption = false;
            if (is_array($item->selected_options) && count($item->selected_options) > 0) {
                foreach ($item->selected_options as $optData) {
                    $optId = $optData['product_option_id'] ?? null;
                    if ($optId && isset($summary[$optId])) {
                        $summary[$optId]['count'] += (int) ($item->quantity ?? 1);
                        $hasOption = true;
                    }
                }
            }

            if (!$hasOption) {
                $noOptionCount += (int) ($item->quantity ?? 1);
            }
        }

        if ($noOptionCount > 0) {
            $summary['no_option'] = [
                'id' => 'no_option',
                'name' => 'Sans déclinaison',
                'count' => $noOptionCount,
            ];
        }

        return array_values($summary);
    }

    /**
     * Retourne uniquement les déclinaisons ayant au moins 1 carte en attente (count > 0)
     */
    #[Computed]
    public function activeIcCardsSummary(): array
    {
        return array_values(array_filter($this->icCardsSummary, fn ($item) => $item['count'] > 0));
    }

    /**
     * Total global de cartes IC en attente de validation
     */
    #[Computed]
    public function totalPendingCards(): int
    {
        return array_sum(array_column($this->icCardsSummary, 'count'));
    }

    /**
     * Badge dynamique dans la barre de navigation indiquant le nombre de cartes IC à venir
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getIcItemsQuery()
            ->where(function (Builder $q) {
                $q->whereDate('service_date', '>=', now()->startOfDay())
                  ->orWhere(function (Builder $q2) {
                      $q2->whereNull('service_date')
                         ->whereHas('folder', function (Builder $fq) {
                             $fq->whereDate('start_date', '>=', now()->startOfDay());
                         });
                  });
            })
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                static::getIcItemsQuery()
                    ->orderByRaw('COALESCE(folder_items.service_date, (SELECT start_date FROM folders WHERE folders.id = folder_items.folder_id)) ASC')
            )
            ->columns([
                Tables\Columns\TextColumn::make('effective_date')
                    ->label('Date Prestation')
                    ->getStateUsing(fn (FolderItem $record) => $record->service_date ?? $record->folder?->start_date)
                    ->date('d/m/Y')
                    ->badge()
                    ->color(function ($state) {
                        if (!$state) return 'gray';
                        $diff = Carbon::now()->startOfDay()->diffInDays(Carbon::parse($state)->startOfDay(), false);
                        if ($diff < 0) return 'gray';
                        if ($diff <= 3) return 'danger';
                        if ($diff <= 7) return 'warning';
                        return 'success';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("COALESCE(folder_items.service_date, (SELECT start_date FROM folders WHERE folders.id = folder_items.folder_id)) {$direction}");
                    }),

                Tables\Columns\TextColumn::make('folder.reference')
                    ->label('Réf. Dossier')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('folder', fn ($q) => $q->where('reference', 'LIKE', "%{$search}%")->orWhere('folder_name', 'LIKE', "%{$search}%"));
                    })
                    ->weight('bold')
                    ->description(fn (FolderItem $record) => $record->folder?->folder_name ?? '---'),

                Tables\Columns\TextColumn::make('folder.lead_traveler_name')
                    ->label('Pax Leader')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('folder', fn ($q) => $q->where('lead_traveler_name', 'LIKE', "%{$search}%"));
                    })
                    ->weight('semibold')
                    ->description(function (FolderItem $record) {
                        $paxTotal = ($record->folder?->pax_adults ?? 0) + ($record->folder?->pax_children ?? 0);
                        return "Dossier : {$paxTotal} pax";
                    }),

                Tables\Columns\TextColumn::make('product_name_display')
                    ->label('Prestation / Carte IC')
                    ->getStateUsing(fn (FolderItem $record) => $record->product?->name ?? $record->title ?? 'Carte IC')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('product', fn ($q) => $q->where('name', 'LIKE', "%{$search}%"))
                                     ->orWhere('title', 'LIKE', "%{$search}%");
                    })
                    ->weight('bold')
                    ->description(function (FolderItem $record) {
                        $options = [];
                        if (is_array($record->selected_options) && count($record->selected_options) > 0) {
                            foreach ($record->selected_options as $opt) {
                                if (!empty($opt['product_option_id'])) {
                                    $optModel = ProductOption::find($opt['product_option_id']);
                                    if ($optModel) {
                                        $options[] = $optModel->name;
                                    }
                                }
                            }
                        }
                        if (count($options) > 0) {
                            return 'Option : ' . implode(', ', $options);
                        }
                        return null;
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qté Cartes')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => "{$state} carte" . ($state > 1 ? 's' : ''))
                    ->sortable(),

                Tables\Columns\TextColumn::make('folder.ticket_dispatch_method')
                    ->label('Livraison / Envoi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'hotel' => '🏨 Hôtel',
                        'guide' => '👤 Guide',
                        'autre' => '📍 Autre',
                        default => '🏨 Hôtel',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'hotel' => 'info',
                        'guide' => 'warning',
                        'autre' => 'primary',
                        default => 'gray',
                    })
                    ->description(fn (FolderItem $record) => Str::limit($record->folder?->dispatch_address ?? '---', 30)),

                Tables\Columns\TextColumn::make('itemStatus.name')
                    ->label('Statut Prestation')
                    ->badge()
                    ->color(fn (FolderItem $record) => $record->itemStatus?->color ?? 'warning')
                    ->formatStateUsing(fn (FolderItem $record) => $record->itemStatus?->name ?? 'En attente')
                    ->sortable(),

                Tables\Columns\TextColumn::make('folder.agency.name')
                    ->label('Agence')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('folder.agency', fn ($q) => $q->where('name', 'LIKE', "%{$search}%"));
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('time_frame')
                    ->label('Échéance')
                    ->options([
                        'upcoming' => '⏳ À venir (Défaut)',
                        'all' => '📋 Toutes les dates',
                        'past' => '📜 Historique / Passées',
                    ])
                    ->default('upcoming')
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? 'upcoming';
                        return match ($value) {
                            'upcoming' => $query->where(function ($q) {
                                $q->whereDate('service_date', '>=', now()->startOfDay())
                                  ->orWhere(function ($q2) {
                                      $q2->whereNull('service_date')
                                         ->whereHas('folder', fn ($fq) => $fq->whereDate('start_date', '>=', now()->startOfDay()));
                                  });
                            }),
                            'past' => $query->where(function ($q) {
                                $q->whereDate('service_date', '<', now()->startOfDay())
                                  ->orWhere(function ($q2) {
                                      $q2->whereNull('service_date')
                                         ->whereHas('folder', fn ($fq) => $fq->whereDate('start_date', '<', now()->startOfDay()));
                                  });
                            }),
                            default => $query,
                        };
                    }),

                SelectFilter::make('item_status_id')
                    ->label('Statut Prestation')
                    ->relationship('itemStatus', 'name'),

                SelectFilter::make('ticket_dispatch_method')
                    ->label('Mode de livraison')
                    ->options([
                        'hotel' => '🏨 Hôtel',
                        'guide' => '👤 Guide',
                        'autre' => '📍 Autre',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            return $query->whereHas('folder', fn ($q) => $q->where('ticket_dispatch_method', $data['value']));
                        }
                        return $query;
                    }),

                Filter::make('service_period')
                    ->form([
                        DatePicker::make('from')->label('Prestation après le'),
                        DatePicker::make('until')->label('Prestation avant le'),
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
            ->recordActions([
                Action::make('updateStatus')
                    ->label('Statut')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->modalHeading('Changer le statut de la Carte IC')
                    ->modalSubmitActionLabel('Mettre à jour')
                    ->form([
                        Select::make('item_status_id')
                            ->label('Nouveau statut')
                            ->options(fn () => ItemStatus::pluck('name', 'id'))
                            ->required()
                            ->default(fn (FolderItem $record) => $record->item_status_id),
                    ])
                    ->action(function (FolderItem $record, array $data) {
                        $record->update(['item_status_id' => $data['item_status_id']]);

                        Notification::make()
                            ->title('Statut mis à jour avec succès')
                            ->success()
                            ->send();
                    }),

                Action::make('openFolder')
                    ->label('Ouvrir Dossier')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->url(fn (FolderItem $record) => \App\Filament\Resources\Folders\FolderResource::getUrl('edit', ['record' => $record->folder_id])),
            ])
            ->bulkActions([
                BulkAction::make('bulkUpdateStatus')
                    ->label('Changer le statut des cartes sélectionnées')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->form([
                        Select::make('item_status_id')
                            ->label('Nouveau statut pour la sélection')
                            ->options(fn () => ItemStatus::pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $records->each(fn (FolderItem $item) => $item->update(['item_status_id' => $data['item_status_id']]));

                        Notification::make()
                            ->title('Statuts mis à jour pour ' . $records->count() . ' cartes IC')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}