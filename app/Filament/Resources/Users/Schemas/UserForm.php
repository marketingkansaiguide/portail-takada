<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Gestion du Profil & Rôle'))
                ->description(__('Configurez l\'identité de l\'utilisateur et ses droits d\'accès au système.'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Nom complet'))
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label(__('Adresse Email'))
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Select::make('role')
                        ->label(__('Rôle / Niveau d\'accès'))
                        ->required()
                        ->options([
                            User::ROLE_SUPER_ADMIN => 'Super Administrateur (Takada)',
                            User::ROLE_ADMIN => 'Administrateur Interne (Takada)',
                            User::ROLE_AGENT => 'Agent de Réservation (Takada)',
                            User::ROLE_AGENCY => 'Client Externe (Agence)',
                        ])
                        ->default(User::ROLE_AGENT)
                        ->searchable()
                        ->preload(),

                    TextInput::make('password')
                        ->label(__('Mot de passe'))
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->required(fn (string $context): bool => $context === 'create')
                        ->placeholder(fn (string $context): ?string => $context === 'edit' ? 'Laissez vide pour ne pas modifier' : null),
                ])->columns(2)
        ]);
    }
}