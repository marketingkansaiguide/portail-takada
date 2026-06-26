<?php

namespace App\Filament\Resources\ClientGroups\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class ClientGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom du Groupe')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('folder_fee')
                    ->label('Frais de dossier')
                    ->money('jpy')
                    ->sortable(),
            ]);
    }
}