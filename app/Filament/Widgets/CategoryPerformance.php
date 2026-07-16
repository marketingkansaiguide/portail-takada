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
    protected static ?string $heading = 'CA & Rentabilité par Catégorie de prestation';

    public function table(Table $table): Table
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        return $table
            ->query(
                Category::query()
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
                    ->label('Catégorie')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Volume (Qté vendue)')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_ca')
                    ->label('Chiffre d\'Affaires')
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('marge_brute')
                    ->label('Marge Dégagée')
                    ->getStateUsing(fn ($record) => $record->total_ca - $record->total_purchase)
                    ->money('JPY')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),

                // 💡 NOUVEAU KPI BONUS : Le taux de marge en pourcentage
                Tables\Columns\TextColumn::make('taux_marge')
                    ->label('Taux de Marge (%)')
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