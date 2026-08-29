<?php

namespace App\Filament\Pages;

use App\Models\FolderItem;
use App\Models\ProductSupplier;
use App\Support\AdminAccess;
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
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class SevenTicketsOrders extends Page implements HasTable
{
    public static function canAccess(): bool
    {
        return AdminAccess::allows('operations.seven_tickets');
    }

    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Commandes Seven Tickets';
    protected static ?string $title = null;

    public function getTitle(): string
    {
        return __('Prestations Seven Tickets');
    }
    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.train-tickets'; // On réutilise ta vue de base Filament si elle contient ton squelette

    /**
     * Requête pour récupérer uniquement les prestations valides pour le Fournisseur 14
     */
    public static function getSevenTicketsItemsQuery(): Builder
    {
        return FolderItem::query()
            ->with(['folder.agency', 'folder.folderPassengers', 'product', 'itemStatus'])
            ->whereHas('folder', function (Builder $q) {
                $q->whereNotIn('status', ['draft', 'cancelled']);
            })
            ->whereHas('itemStatus', function (Builder $sq) {
                // On s'assure de ne prendre que les prestations validées ou confirmées
                $sq->where('name', 'like', '%ouverture%')
                   ->orWhere('name', 'like', '%Validé%')
                   ->orWhere('name', 'like', '%Confirmé%')
                   ->orWhere('id', 1);
            })
            ->where(function (Builder $query) {
                // Prestations affectées explicitement au fournisseur 14
                $query->where('supplier_id', 14)
                      // Ou prestations dont le produit de base est lié au fournisseur 14
                      ->orWhereHas('product.productSuppliers', function ($sq) {
                          $sq->where('supplier_id', 14);
                      });
            });
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getSevenTicketsItemsQuery()
            ->whereNull('label_exported_at')
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
                static::getSevenTicketsItemsQuery()
                    ->orderByRaw('COALESCE(folder_items.service_date, (SELECT start_date FROM folders WHERE folders.id = folder_items.folder_id)) ASC')
            )
            ->columns([
                Tables\Columns\TextColumn::make('service_date')
                    ->label(__('Date'))
                    ->getStateUsing(fn (FolderItem $record) => $record->service_date ?? $record->folder?->start_date)
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('folder.folder_name')
                    ->label(__('Dossier'))
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (FolderItem $record) => $record->folder?->lead_traveler_name),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Prestation'))
                    ->wrap()
                    ->description(function (FolderItem $record) {
                        $customVals = is_string($record->custom_values) ? json_decode($record->custom_values, true) : ($record->custom_values ?? []);
                        $optionId = $customVals['option_id'] ?? $record->product_option_id ?? null;
                        if ($optionId) {
                            return \App\Models\ProductOption::find($optionId)?->name;
                        }
                        return null;
                    }),

                Tables\Columns\TextColumn::make('pax_count')
                    ->label(__('Passagers'))
                    ->getStateUsing(function (FolderItem $record) {
                        $pax = $record->getPaxBreakdown();

                        return $pax['has_age_breakdown']
                            ? "{$pax['adults']} AD" . ($pax['teenagers'] > 0 ? " / {$pax['teenagers']} ADO" : '') . " / {$pax['children']} ENF"
                            : "{$pax['total']} PAX";
                    }),

                Tables\Columns\TextColumn::make('itemStatus.name')
                    ->label(__('Statut'))
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('label_exported_at')
                    ->label(__('Statut Étiquettes'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('✅ Imprimée') : __('⏳ En attente'))
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->description(fn (FolderItem $record) => $record->label_exported_at ? Carbon::parse($record->label_exported_at)->format('d/m/Y à H:i') : null)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('label_exported')
                    ->label(__('Statut Impression'))
                    ->options([
                    'pending' => __('⏳ En attente (Non imprimé)'),
                    'exported' => __('✅ Imprimé'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'pending' => $query->whereNull('label_exported_at'),
                            'exported' => $query->whereNotNull('label_exported_at'),
                            default => $query,
                        };
                    })
                    ->attribute('id'),

                Filter::make('service_period')
                    ->form([
                        DatePicker::make('from')->label(__('Prestation après le')),
                        DatePicker::make('until')->label(__('Prestation avant le')),
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
                BulkAction::make('print_labels')
                    ->label(__('Imprimer les étiquettes'))
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->action(function (Collection $records, $livewire) {
                        
                        // 1. On marque les dossiers sélectionnés comme exportés/imprimés
                        FolderItem::whereIn('id', $records->pluck('id'))->update(['label_exported_at' => now()]);

                        // Même prévisualisation modifiable que les étiquettes de check-in.
                        $ids = $records->pluck('id')->implode(',');
                        $url = route('pdf.seven-tickets-labels', ['ids' => $ids]);
                        $livewire->js("window.open('{$url}', '_blank')");
                    }),
            ]);
    }
}
