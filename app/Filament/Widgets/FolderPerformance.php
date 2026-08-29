<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\Folder;
use App\Models\FolderItem;

class FolderPerformance extends BaseWidget
{
    use InteractsWithPageFilters;

    // 💡 Désactivation du Lazy Loading pour Windows
    protected static bool $isLazy = false; 
    protected static bool $isDiscovered = false;
    
    protected int | string | array $columnSpan = 'full';
    
    // 💡 FIX : REMISE du mot-clé "static" exigé par TableWidget
    protected static ?string $heading = null;

    protected function getTableHeading(): ?string
    {
        return __('Dossiers les plus importants');
    }

    protected function getTableDescription(): ?string
    {
        return __('Dossiers confirmés ou terminés, classés par chiffre d’affaires.');
    }

    public function table(Table $table): Table
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        return $table
            ->query(
                Folder::query()
                    ->whereIn('status', ['confirmed', 'completed'])
                    ->where('total_price', '>', 0)
                    ->when($startDate, fn ($query) => $query->whereDate('created_at', '>=', $startDate))
                    ->when($endDate, fn ($query) => $query->whereDate('created_at', '<=', $endDate))
                    ->addSelect([
                        'total_purchase' => FolderItem::selectRaw('COALESCE(SUM(purchase_total_price), 0)')
                            ->whereColumn('folder_id', 'folders.id'),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                ->label(__('Réf.'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('folder_name')
                ->label(__('Nom du voyage'))
                    ->searchable()
                    ->limit(25),
                    
                Tables\Columns\TextColumn::make('agency.name')
                    ->label(__('Agence'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('pax')
                ->label(__('Pax'))
                    ->getStateUsing(fn ($record) => $record->pax_adults + $record->pax_children)
                    ->numeric()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_price')
                ->label(__('Vente (¥)'))
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_purchase')
                ->label(__('Achat (¥)'))
                    ->money('JPY')
                    ->sortable(),

                Tables\Columns\TextColumn::make('marge_brute')
                ->label(__('Marge brute (¥)'))
                    ->getStateUsing(fn ($record) => $record->total_price - $record->total_purchase)
                    ->money('JPY')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('taux_marge')
                ->label(__('Marge (%)'))
                    ->getStateUsing(function ($record) {
                        $marge = $record->total_price - $record->total_purchase;
                        return $record->total_price > 0 ? ($marge / $record->total_price) * 100 : 0;
                    })
                    ->numeric(1)
                    ->suffix('%')
                    ->color(fn ($state) => $state >= 20 ? 'success' : ($state >= 10 ? 'warning' : 'danger')),
            ])
            ->defaultSort('total_price', 'desc')
            ->striped();
    }
}
