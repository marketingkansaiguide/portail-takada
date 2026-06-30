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
                    ->label(__('Photo'))
                    ->circular()
                    ->stacked()
                    ->limit(3),

                TextColumn::make('name')
                    ->label(__('Prestation'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('supplier.name')
                    ->label(__('Fournisseur'))
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label(__('Catégorie'))
                    ->badge()
                    ->sortable(),

                IconColumn::make('is_lottery')
                    ->label(__('Loterie'))
                    ->boolean(),

                IconColumn::make('is_on_demand')
                    ->label(__('Devis'))
                    ->boolean(),
            ]);
    }
}