<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nom'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('contact_name')
                    ->label(__('Contact'))
                    ->searchable(),

                TextColumn::make('phone')
                    ->label(__('Téléphone')),

                // 💡 NOUVELLE COLONNE
                TextColumn::make('payment_type')
                    ->label(__('Paiement'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('commission')
                    ->label(__('Commission'))
                    ->suffix(' %')
                    ->sortable(),
            ]);
    }
}
