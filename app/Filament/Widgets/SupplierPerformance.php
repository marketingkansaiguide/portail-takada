<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Supplier;
use App\Models\FolderItem;

class SupplierPerformance extends BaseWidget
{
    protected static bool $isLazy = false;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Volume d\'Affaires par Fournisseur';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Supplier::query()
                    ->addSelect([
                        'folders_count' => FolderItem::selectRaw('COUNT(DISTINCT folder_items.folder_id)')
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('folder_items.supplier_id', 'suppliers.id')
                            ->whereIn('folders.status', ['confirmed', 'completed']),

                        'total_ca' => FolderItem::selectRaw('COALESCE(SUM(folder_items.total_price), 0)')
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('folder_items.supplier_id', 'suppliers.id')
                            ->whereIn('folders.status', ['confirmed', 'completed']),

                        'total_purchase' => FolderItem::selectRaw('COALESCE(SUM(folder_items.purchase_total_price), 0)')
                            ->join('folders', 'folders.id', '=', 'folder_items.folder_id')
                            ->whereColumn('folder_items.supplier_id', 'suppliers.id')
                            ->whereIn('folders.status', ['confirmed', 'completed']),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Fournisseur partenaire')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('folders_count')
                    ->label('Sollicité dans X Dossiers')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_purchase')
                    ->label('Ce qu\'on leur a payé (Achat ¥)')
                    ->money('JPY')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_ca')
                    ->label('CA Généré via eux (Vente ¥)')
                    ->money('JPY')
                    ->weight('bold')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('marge_brute')
                    ->label('Marge Conservée (¥)')
                    ->getStateUsing(fn ($record) => $record->total_ca - $record->total_purchase)
                    ->money('JPY')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('marge_moyenne')
                    ->label('Marge Moy. / Dossier')
                    ->getStateUsing(fn ($record) => $record->folders_count > 0 ? ($record->total_ca - $record->total_purchase) / $record->folders_count : 0)
                    ->money('JPY'),
            ])
            ->defaultSort('total_ca', 'desc')
            ->striped();
    }
}