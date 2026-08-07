<?php

namespace App\Filament\Pages;

use App\Models\FolderItem;
use App\Models\ItemStatus;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class TrainTickets extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Billets de Train';
    protected static ?string $title = 'Gestion & Suivi des Billets de Train à venir';
    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.train-tickets';

    /**
     * Requête pour cibler les billets de transport (trains)
     */
    public static function getTrainItemsQuery(): Builder
    {
        return FolderItem::query()
            ->with([
                'folder.agency',
                'folder.mainSeller',
                'product.category',
                'itemStatus',
                'supplier',
            ])
            ->whereHas('folder', function (Builder $q) {
                $q->where('status', 'confirmed');
            })
            ->whereHas('product', function (Builder $q) {
                $q->where('product_type', 'transport');
            })
            // Filtre : "Confirmé", "Validé" ou "Ouverture des réservations"
            ->whereHas('itemStatus', function (Builder $q) {
                $q->where('name', 'like', '%Confirmé%')
                  ->orWhere('name', 'like', '%Validé%')
                  ->orWhere('name', 'like', '%ouverture%'); 
            });
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getTrainItemsQuery()
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
        return 'success';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                static::getTrainItemsQuery()
                    ->orderByRaw('COALESCE(folder_items.service_date, (SELECT start_date FROM folders WHERE folders.id = folder_items.folder_id)) ASC')
            )
            ->columns([
                Tables\Columns\TextColumn::make('effective_date')
                    ->label('Date Globale')
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

                Tables\Columns\TextColumn::make('transport_details')
                    ->label('Détails des Trajets')
                    ->html()
                    ->getStateUsing(function (FolderItem $record) {
                        $customVals = $record->custom_values ?? [];
                        if (!is_array($customVals) || empty($customVals['transport_routes'])) {
                            return '<span class="text-gray-500 italic">Aucun trajet défini</span>';
                        }
                        
                        $routesHtml = [];
                        foreach ($customVals['transport_routes'] as $r) {
                            $dep = e($r['departure_station'] ?? '?');
                            $arr = e($r['arrival_station'] ?? '?');
                            $date = !empty($r['departure_date']) ? Carbon::parse($r['departure_date'])->format('d/m/Y') : '---';
                            $time = !empty($r['departure_time']) ? ' à ' . e($r['departure_time']) : '';
                            $pax = !empty($r['pax_count']) ? e($r['pax_count']) . ' pax' : '';
                            
                            $routesHtml[] = "
                                <div style='margin-bottom: 6px; padding: 4px; background-color: #f8fafc; border-radius: 4px; border: 1px solid #e2e8f0;'>
                                    <strong style='color: #0f172a;'>{$dep} ➔ {$arr}</strong><br>
                                    <span style='font-size: 0.85rem; color: #64748b;'>📅 {$date}{$time} | 👥 {$pax}</span>
                                </div>
                            ";
                        }
                        return implode('', $routesHtml);
                    }),

                Tables\Columns\TextColumn::make('itemStatus.name')
                    ->label('Statut Presta.')
                    ->badge()
                    ->color(fn (FolderItem $record) => $record->itemStatus?->color ?? 'success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('label_exported_at')
                    ->label('Statut Étiquette')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? '🏷️ Exportée' : '⏳ Non exportée')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->description(fn (FolderItem $record) => $record->label_exported_at ? 'Le ' . Carbon::parse($record->label_exported_at)->format('d/m/Y à H:i') : null)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('label_exported')
                    ->label('Statut Étiquette')
                    ->options([
                        'pending' => '⏳ Non exportée',
                        'exported' => '🏷️ Exportée',
                    ])
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
            ->recordActions([
                Action::make('updateStatus')
                    ->label('Statut')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->form([
                        Select::make('item_status_id')
                            ->label('Nouveau statut')
                            ->options(fn () => ItemStatus::pluck('name', 'id'))
                            ->required()
                            ->default(fn (FolderItem $record) => $record->item_status_id),
                    ])
                    ->action(function (FolderItem $record, array $data) {
                        $record->item_status_id = $data['item_status_id'];
                        $record->save();
                        
                        Notification::make()->title('Statut mis à jour')->success()->send();
                    }),

                Action::make('toggleLabelExport')
                    ->label(fn (FolderItem $record) => $record->label_exported_at ? 'Marquer non exportée' : 'Marquer exportée')
                    ->icon(fn (FolderItem $record) => $record->label_exported_at ? 'heroicon-o-arrow-path' : 'heroicon-o-check-circle')
                    ->color(fn (FolderItem $record) => $record->label_exported_at ? 'gray' : 'success')
                    ->action(function (FolderItem $record) {
                        $record->label_exported_at = $record->label_exported_at ? null : now();
                        $record->save();
                    }),

                Action::make('openFolder')
                    ->label('Dossier')
                    ->icon('heroicon-o-folder-open')
                    ->color('primary')
                    ->url(fn (FolderItem $record) => \App\Filament\Resources\Folders\FolderResource::getUrl('edit', ['record' => $record->folder_id])),
            ])
            ->bulkActions([
                BulkAction::make('export_train_labels')
                    ->label('Imprimer les étiquettes')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->action(function (Collection $records, $livewire) {
                        $records->each(function ($record) {
                            $record->label_exported_at = now();
                            $record->save();
                        });

                        $ids = $records->pluck('id')->implode(',');
                        $url = route('pdf.train-labels', ['ids' => $ids]);
                        
                        $livewire->js("window.open('{$url}', '_blank')");
                    }),
            ]);
    }
}