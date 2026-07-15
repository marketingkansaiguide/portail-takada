<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Agency;
use App\Models\Folder;
use App\Models\FolderItem;

class AgencyPerformance extends BaseWidget
{
    protected static bool $isLazy = false;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Performances & Rentabilité par Agence';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Agency::query()
                    ->addSelect([
                        // Nombre total de dossiers confirmés/terminés pour cette agence
                        'folders_count' => Folder::selectRaw('count(*)')
                            ->whereColumn('agency_id', 'agencies.id')
                            ->whereIn('status', ['confirmed', 'completed']),

                        // CA Total (Vente)
                        'total_ca' => Folder::selectRaw('COALESCE(SUM(total_price), 0)')
                            ->whereColumn('agency_id', 'agencies.id')
                            ->whereIn('status', ['confirmed', 'completed']),

                        // Coût d'achat total des prestations contenues dans ces dossiers
                        'total_purchase' => FolderItem::selectRaw('COALESCE(SUM(purchase_total_price), 0)')
                            ->whereIn('folder_id', function ($query) {
                                $query->select('id')->from('folders')
                                    ->whereColumn('agency_id', 'agencies.id')
                                    ->whereIn('status', ['confirmed', 'completed']);
                            }),

                        // Nombre total de prestations réservées
                        'items_count' => FolderItem::selectRaw('count(*)')
                            ->whereIn('folder_id', function ($query) {
                                $query->select('id')->from('folders')
                                    ->whereColumn('agency_id', 'agencies.id')
                                    ->whereIn('status', ['confirmed', 'completed']);
                            }),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom de l\'Agence')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('folders_count')
                    ->label('Nb. Dossiers')
                    ->badge()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_ca')
                    ->label('CA Total (¥)')
                    ->money('JPY')
                    ->weight('bold')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('marge_brute')
                    ->label('Marge Globale')
                    ->getStateUsing(fn ($record) => $record->total_ca - $record->total_purchase)
                    ->money('JPY')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('marge_moyenne')
                    ->label('Marge Moy. / Dossier')
                    ->getStateUsing(fn ($record) => $record->folders_count > 0 ? ($record->total_ca - $record->total_purchase) / $record->folders_count : 0)
                    ->money('JPY'),
                    
                Tables\Columns\TextColumn::make('prestations_moyennes')
                    ->label('Moy. Prestations / Dossier')
                    ->getStateUsing(fn ($record) => $record->folders_count > 0 ? round($record->items_count / $record->folders_count, 1) : 0)
                    ->numeric(1)
                    ->color('info'),
            ])
            ->defaultSort('total_ca', 'desc')
            ->striped();
    }
}