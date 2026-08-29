<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters; // 💡 Ajout pour les filtres
use App\Models\Category;
use App\Models\FolderItem;

class CategoryPerformance extends BaseWidget
{
    use InteractsWithPageFilters; // 💡 Activation des filtres
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false; // 💡 Sécurité anti-crash Windows
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = null;

    protected function getTableHeading(): ?string
    {
        return __('Performance par catégorie');
    }

    protected function getTableDescription(): ?string
    {
        return __('Uniquement les catégories présentes dans des dossiers confirmés ou terminés.');
    }

    public function table(Table $table): Table
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        return $table
            ->query(
                Category::query()
                    ->whereExists(function ($query) use ($startDate, $endDate) {
                        $query->selectRaw('1')
                            ->from('folder_items')
                            ->join('products', 'products.id', '=', 'folder_items.product_id')
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('products.category_id', 'categories.id')
                            ->whereIn('folders.status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('folders.created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('folders.created_at', '<=', $endDate));
                    })
                    ->addSelect([
                        'items_count' => FolderItem::selectRaw('COUNT(*)')
                            ->join('products', 'products.id', '=', 'folder_items.product_id')
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('products.category_id', 'categories.id')
                            ->whereIn('folders.status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('folders.created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('folders.created_at', '<=', $endDate)),

                        'total_ca' => FolderItem::selectRaw('COALESCE(SUM(folder_items.total_price), 0)')
                            ->join('products', 'products.id', '=', 'folder_items.product_id')
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('products.category_id', 'categories.id')
                            ->whereIn('folders.status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('folders.created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('folders.created_at', '<=', $endDate)),

                        'total_purchase' => FolderItem::selectRaw('COALESCE(SUM(folder_items.purchase_total_price), 0)')
                            ->join('products', 'products.id', '=', 'folder_items.product_id')
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('products.category_id', 'categories.id')
                            ->whereIn('folders.status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('folders.created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('folders.created_at', '<=', $endDate)),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Catégorie'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('items_count')
                ->label(__('Prestations vendues'))
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_ca')
                ->label(__('Chiffre d\'Affaires'))
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('marge_brute')
                ->label(__('Marge brute'))
                    ->getStateUsing(fn ($record) => $record->total_ca - $record->total_purchase)
                    ->money('JPY')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('taux_marge')
                ->label(__('Marge (%)'))
                    ->getStateUsing(function ($record) {
                        $marge = $record->total_ca - $record->total_purchase;
                        return $record->total_ca > 0 ? ($marge / $record->total_ca) * 100 : 0;
                    })
                    ->numeric(1)
                    ->suffix('%')
                    ->color('gray'),
            ])
            ->defaultSort('total_ca', 'desc')
            ->striped();
    }
}
