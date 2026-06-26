<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Tables\Columns\TextColumn;

class SuppliersTable
{
    public static function schema(): array
    {
        return [
            TextColumn::make('name')
                ->label('Nom')
                ->searchable()
                ->sortable(),

            TextColumn::make('contact_name')
                ->label('Contact')
                ->searchable(),

            TextColumn::make('phone')
                ->label('Téléphone'),

            TextColumn::make('commission')
                ->label('Commission')
                ->suffix(' %')
                ->sortable(),
        ];
    }
}