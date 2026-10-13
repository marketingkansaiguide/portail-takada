<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Gestion du compte utilisateur'))
                ->description(__('Configurez l\'identité, les profils (cumulables) et le rattachement de l\'utilisateur.'))
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

                    Select::make('roles')
                        ->label(__('Profils (cumulables)'))
                        ->relationship(
                            'roles',
                            'display_name',
                            // Seuls les (super) administrateurs peuvent attribuer les profils d'administration.
                            modifyQueryUsing: function (Builder $query) {
                                $user = auth('web')->user();
                                if (! $user?->hasRole(User::ROLE_SUPER_ADMIN)) {
                                    $query->whereNotIn('id', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN]);
                                }
                            }
                        )
                        ->multiple()
                        ->preload()
                        ->required()
                        ->live(),

                    // Apparaît uniquement si le profil "Agence partenaire" est sélectionné
                    Select::make('agency_id')
                        ->relationship('agency', 'name')
                        ->label(__('Agence de rattachement'))
                        ->placeholder(__('Sélectionnez l\'agence parente'))
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get) => in_array('agency', (array) $get('roles'), true))
                        ->visible(fn (Get $get) => in_array('agency', (array) $get('roles'), true)),
                ])
        ]);
    }
}
