<?php

namespace App\Filament\Resources\Agencies\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
// 💡 IMPORTATIONS UNIFIÉES : Utilisation des classes d'actions globales de votre projet
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Vendeurs / Utilisateurs de l\'agence';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nom du vendeur'))
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Adresse e-mail'))
                    ->searchable(),
            ])
            ->filters([])
            ->headerActions([
                // 💡 CORRECTION : Utilisation de la classe CreateAction globale
                CreateAction::make()
                    ->label(__('Ajouter un vendeur'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['role'] = \App\Models\User::ROLE_AGENCY; // Force le rôle d'agence
                        return $data;
                    }),
            ])
            // 💡 CORRECTION : Remplacement par recordActions() avec les classes d'actions globales
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}