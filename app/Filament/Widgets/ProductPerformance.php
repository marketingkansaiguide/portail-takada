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
    protected static ?string $heading = 'Popularité & Rentabilité par Prestation (Produit)';

    public function table(Table $table): Table
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        return $table
            ->query(
                Product::query()
                    ->addSelect([
                        // Nombre de dossiers distincts contenant ce produit
                        'folders_count' => FolderItem::selectRaw('COUNT(DISTINCT folder_items.folder_id)')
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('folder_items.product_id', 'products.id')
                            ->whereIn('folders.status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('folders.created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('folders.created_at', '<=', $endDate)),

                        // Somme totale des ventes pour ce produit
                        'total_ca' => FolderItem::selectRaw('COALESCE(SUM(folder_items.total_price), 0)')
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('folder_items.product_id', 'products.id')
                            ->whereIn('folders.status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('folders.created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('folders.created_at', '<=', $endDate)),

                        // Somme totale des achats pour ce produit
                        'total_purchase' => FolderItem::selectRaw('COALESCE(SUM(folder_items.purchase_total_price), 0)')
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('folder_items.product_id', 'products.id')
                            ->whereIn('folders.status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('folders.created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('folders.created_at', '<=', $endDate)),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom de la prestation')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('folders_count')
                    ->label('Présent dans X Dossiers')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_ca')
                    ->label('CA Généré (¥)')
                    ->money('JPY')
                    ->weight('bold')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('marge_brute')
                    ->label('Marge Totale (¥)')
                    ->getStateUsing(fn ($record) => $record->total_ca - $record->total_purchase)
                    ->money('JPY')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('marge_moyenne')
                    ->label('Marge Moy. / Dossier incluant ce produit')
                    ->getStateUsing(fn ($record) => $record->folders_count > 0 ? ($record->total_ca - $record->total_purchase) / $record->folders_count : 0)
                    ->money('JPY'),
            ])
            ->defaultSort('total_ca', 'desc')
            ->striped();
    }
}