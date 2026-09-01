<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters; // 💡 Ajout pour les filtres
use App\Models\Product;
use App\Models\FolderItem;

class ProductPerformance extends BaseWidget
{
    use InteractsWithPageFilters; // 💡 Activation des filtres
    protected static bool $isDiscovered = false;
    protected static bool $isLazy = false;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = null;

    protected function getTableHeading(): ?string
    {
        return __('Prestations les plus performantes');
    }

    protected function getTableDescription(): ?string
    {
        return __('Classement par chiffre d’affaires des prestations réellement vendues.');
    }

    public function table(Table $table): Table
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        return $table
            ->query(
                Product::query()
                    ->whereExists(function ($query) use ($startDate, $endDate) {
                        $query->selectRaw('1')
                            ->from('folder_items')
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('folder_items.product_id', 'products.id')
                            ->whereIn('folders.status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('folders.created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('folders.created_at', '<=', $endDate));
                    })
                    ->addSelect([
                        'folders_count' => FolderItem::selectRaw('COUNT(DISTINCT folder_items.folder_id)')
                            ->notCancelled()
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('folder_items.product_id', 'products.id')
                            ->whereIn('folders.status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('folders.created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('folders.created_at', '<=', $endDate)),

                        'total_ca' => FolderItem::selectRaw('COALESCE(SUM(folder_items.total_price), 0)')
                            ->notCancelled()
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('folder_items.product_id', 'products.id')
                            ->whereIn('folders.status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('folders.created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('folders.created_at', '<=', $endDate)),

                        'total_purchase' => FolderItem::selectRaw('COALESCE(SUM(folder_items.purchase_total_price), 0)')
                            ->notCancelled()
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('folder_items.product_id', 'products.id')
                            ->whereIn('folders.status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('folders.created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('folders.created_at', '<=', $endDate)),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                ->label(__('Nom de la prestation'))
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('folders_count')
                ->label(__('Dossiers'))
                    ->badge()
                    ->color('info')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_ca')
                ->label(__('CA Généré (¥)'))
                    ->money('JPY')
                    ->weight('bold')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('marge_brute')
                ->label(__('Marge brute (¥)'))
                    ->getStateUsing(fn ($record) => $record->total_ca - $record->total_purchase)
                    ->money('JPY')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('marge_moyenne')
                ->label(__('Marge moy. / dossier'))
                    ->getStateUsing(fn ($record) => $record->folders_count > 0 ? ($record->total_ca - $record->total_purchase) / $record->folders_count : 0)
                    ->money('JPY'),
            ])
            ->defaultSort('total_ca', 'desc')
            ->striped();
    }
}
