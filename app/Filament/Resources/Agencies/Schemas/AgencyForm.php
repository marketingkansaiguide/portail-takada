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
            Section::make(__('Informations de l\'Agence'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Nom de l\'agence'))
                        ->required(),

                    Select::make('client_group_id')
                        ->relationship('clientGroup', 'name')
                        ->label(__('Groupe Client (Tarification)'))
                        ->required()
                        ->searchable()
                        ->preload(),

                    TextInput::make('contact_name')
                        ->label(__('Nom du contact')),

                    TextInput::make('email')
                        ->label(__('Email'))
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),

                    TextInput::make('phone')
                        ->label(__('Téléphone'))
                        ->tel(),

                    Toggle::make('is_approved')
                        ->label(__('Compte approuvé'))
                        ->helperText(__('Activez pour autoriser cette agence à se connecter.'))
                        ->default(false),

                    Textarea::make('address')
                        ->label(__('Adresse postale complète'))
                        ->columnSpanFull(),
                ])->columns(2)
        ]);
    }
}