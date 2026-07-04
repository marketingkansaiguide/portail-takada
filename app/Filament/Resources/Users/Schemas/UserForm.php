<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Gestion du compte utilisateur'))
                ->description(__('Configurez l\'identité, le rôle et le rattachement de l\'utilisateur.'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('Nom complet'))
                        ->required()
                        ->placeholder('Ex: Jean Vendeur'),

                    TextInput::make('email')
                        ->label(__('Adresse e-mail'))
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->placeholder('Ex: vendeur@monagence.com'),

                    TextInput::make('password')
                        ->label(__('Mot de passe'))
                        ->password()
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord)
                        ->placeholder(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord ? __('Laissez vide pour inchangé') : '********'),

                    Select::make('role')
                        ->label(__('Rôle / Profil applicatif'))
                        ->options([
                            'super_admin' => __('Super Administrateur'),
                            'admin' => __('Administrateur Takada'),
                            'agent' => __('Agent interne Takada'),
                            'agency' => __('Agence Partenaire (B2B)'),
                        ])
                        ->required()
                        ->live(), // Rend le champ réactif pour l'affichage de l'agence

                    // 💡 MAILLON CLÉ : Apparaît uniquement si le profil sélectionné est une agence B2B
                    Select::make('agency_id')
                        ->relationship('agency', 'name')
                        ->label(__('Agence de rattachement'))
                        ->placeholder(__('Sélectionnez l\'agence parente'))
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get) => $get('role') === 'agency')
                        ->visible(fn (Get $get) => $get('role') === 'agency'),
                ])
        ]);
    }
}