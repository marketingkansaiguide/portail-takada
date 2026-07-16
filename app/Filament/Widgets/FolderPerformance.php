<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Folder;
use App\Models\FolderItem;

class FolderPerformance extends BaseWidget
{
    // 💡 Désactivation du Lazy Loading pour Windows
    protected static bool $isLazy = false; 
    protected static bool $isDiscovered = false;
    
    protected int | string | array $columnSpan = 'full';
    
    // 💡 FIX : REMISE du mot-clé "static" exigé par TableWidget
    protected static ?string $heading = 'Rentabilité détaillée par Dossier';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Folder::query()
                    ->whereIn('status', ['confirmed', 'completed'])
                    ->addSelect([
                        'total_purchase' => FolderItem::selectRaw('COALESCE(SUM(purchase_total_price), 0)')
                            ->whereColumn('folder_id', 'folders.id'),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Réf.')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('folder_name')
                    ->label('Nom du voyage')
                    ->searchable()
                    ->limit(25),
                    
                Tables\Columns\TextColumn::make('agency.name')
                    ->label('Agence')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pax')
                    ->label('Pax')
                    ->getStateUsing(fn ($record) => $record->pax_adults + $record->pax_children)
                    ->numeric()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Vente (¥)')
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_purchase')
                    ->label('Achat (¥)')
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('marge_brute')
                    ->label('Marge Nette (¥)')
                    ->getStateUsing(fn ($record) => $record->total_price - $record->total_purchase)
                    ->money('JPY')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('taux_marge')
                    ->label('Marge (%)')
                    ->getStateUsing(function ($record) {
                        $marge = $record->total_price - $record->total_purchase;
                        return $record->total_price > 0 ? ($marge / $record->total_price) * 100 : 0;
                    })
                    ->numeric(1)
                    ->suffix('%')
                    ->color(fn ($state) => $state >= 20 ? 'success' : ($state >= 10 ? 'warning' : 'danger')),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }
}