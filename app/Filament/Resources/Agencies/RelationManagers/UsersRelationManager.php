<?php

namespace App\Filament\Resources\Agencies\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

// 💡 RETOUR AUX IMPORTATIONS UNIFIÉES DE VOTRE PROJET
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Vendeurs / Utilisateurs de l\'agence';

    /**
     * Déclaration des champs du formulaire avec votre structure de "Schema"
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Nom du vendeur'))
                    ->required()
                    ->placeholder('Ex: Jean Dupont'),

                TextInput::make('email')
                    ->label(__('Adresse e-mail'))
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->placeholder('Ex: vendeur@agence.com'),

                TextInput::make('password')
                    ->label(__('Mot de passe'))
                    ->password()
                    // Requis uniquement à la création. À l'édition, s'il est vide, le mot de passe n'est pas modifié.
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->placeholder(fn (string $operation): string => $operation === 'edit' ? __('Laissez vide pour conserver l\'actuel') : '********'),
            ]);
    }

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
                CreateAction::make()
                    ->label(__('Ajouter un vendeur'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['role'] = \App\Models\User::ROLE_AGENCY; // Force le rôle d'agence
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}