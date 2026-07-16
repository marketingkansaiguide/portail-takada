<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\Agency;
use App\Models\Folder;
use App\Models\FolderItem;

class AgencyPerformance extends BaseWidget
{
    use InteractsWithPageFilters; 

    protected static bool $isDiscovered = false; 
    protected static bool $isLazy = false;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Performances & Rentabilité par Agence';

    public function table(Table $table): Table
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        return $table
            ->query(
                Agency::query()
                    ->addSelect([
                        'folders_count' => Folder::selectRaw('count(*)')
                            ->whereColumn('agency_id', 'agencies.id')
                            ->whereIn('status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('created_at', '<=', $endDate)),

                        'total_ca' => Folder::selectRaw('COALESCE(SUM(total_price), 0)')
                            ->whereColumn('agency_id', 'agencies.id')
                            ->whereIn('status', ['confirmed', 'completed'])
                            ->when($startDate, fn ($query) => $query->whereDate('created_at', '>=', $startDate))
                            ->when($endDate, fn ($query) => $query->whereDate('created_at', '<=', $endDate)),

                        'total_purchase' => FolderItem::selectRaw('COALESCE(SUM(purchase_total_price), 0)')
                            ->whereIn('folder_id', function ($query) use ($startDate, $endDate) {
                                $query->select('id')->from('folders')
                                    ->whereColumn('agency_id', 'agencies.id')
                                    ->whereIn('status', ['confirmed', 'completed'])
                                    ->when($startDate, fn ($q) => $q->whereDate('created_at', '>=', $startDate))
                                    ->when($endDate, fn ($q) => $q->whereDate('created_at', '<=', $endDate));
                            }),

                        'items_count' => FolderItem::selectRaw('count(*)')
                            ->whereIn('folder_id', function ($query) use ($startDate, $endDate) {
                                $query->select('id')->from('folders')
                                    ->whereColumn('agency_id', 'agencies.id')
                                    ->whereIn('status', ['confirmed', 'completed'])
                                    ->when($startDate, fn ($q) => $q->whereDate('created_at', '>=', $startDate))
                                    ->when($endDate, fn ($q) => $q->whereDate('created_at', '<=', $endDate));
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
                    
                // 💡 LA NOUVELLE COLONNE : Taux de marge (%)
                Tables\Columns\TextColumn::make('taux_marge')
                    ->label('Marge (%)')
                    ->getStateUsing(function ($record) {
                        $marge = $record->total_ca - $record->total_purchase;
                        return $record->total_ca > 0 ? ($marge / $record->total_ca) * 100 : 0;
                    })
                    ->numeric(1)
                    ->suffix('%')
                    ->color(fn ($state) => $state >= 20 ? 'success' : ($state >= 10 ? 'warning' : 'danger')),
                    
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