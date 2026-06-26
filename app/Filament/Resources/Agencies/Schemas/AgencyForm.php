<?php

namespace App\Filament\Resources\Agencies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AgencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informations de l\'Agence')
                ->schema([
                    TextInput::make('name')
                        ->label('Nom de l\'agence')
                        ->required(),

                    // Ce champ va automatiquement chercher les groupes dans ta base !
                    Select::make('client_group_id')
                        ->relationship('clientGroup', 'name')
                        ->label('Groupe Client (Tarification)')
                        ->required()
                        ->searchable()
                        ->preload(),

                    TextInput::make('contact_name')
                        ->label('Nom du contact'),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),

                    TextInput::make('phone')
                        ->label('Téléphone')
                        ->tel(),

                    Toggle::make('is_approved')
                        ->label('Compte approuvé')
                        ->helperText('Activez pour autoriser cette agence à se connecter.')
                        ->default(false),

                    Textarea::make('address')
                        ->label('Adresse postale complète')
                        ->columnSpanFull(),
                ])->columns(2)
        ]);
    }
}