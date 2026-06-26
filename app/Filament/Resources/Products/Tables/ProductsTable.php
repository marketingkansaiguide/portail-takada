<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('images')
                    ->label('Photo')
                    ->circular()
                    ->stacked()
                    ->limit(3), // Affiche jusqu'à 3 bulles de photos empilées

                TextColumn::make('name')
                    ->label('Prestation')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('supplier.name')
                    ->label('Fournisseur')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Catégorie')
                    ->badge(),

                IconColumn::make('is_lottery')
                    ->label('Loterie')
                    ->boolean(),

                IconColumn::make('is_on_demand')
                    ->label('Devis')
                    ->boolean(),
            ]);
    }
}