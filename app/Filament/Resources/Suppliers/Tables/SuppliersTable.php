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
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('Téléphone'),

                // 💡 NOUVELLE COLONNE
                TextColumn::make('payment_type')
                    ->label('Paiement')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('commission')
                    ->label('Commission')
                    ->suffix(' %')
                    ->sortable(),
            ]);
    }
}