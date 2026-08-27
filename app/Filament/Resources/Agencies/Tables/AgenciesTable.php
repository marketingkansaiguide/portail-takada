<?php

namespace App\Filament\Resources\Agencies\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;

class AgenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Agence'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('clientGroup.name')
                    ->label(__('Groupe'))
                    ->badge() // Affiche le nom du groupe dans une jolie bulle colorée
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable(),

                // Un bouton direct dans le tableau pour approuver/bloquer l'agence en un clic !
                ToggleColumn::make('is_approved')
                    ->label(__('Approuvé'))
                    ->sortable(),
            ]);
    }
}
