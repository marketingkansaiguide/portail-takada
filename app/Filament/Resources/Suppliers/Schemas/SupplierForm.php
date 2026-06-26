<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informations Fournisseur')
                ->description('Coordonnées et paramètres de commission')
                ->schema([
                    TextInput::make('name')
                        ->label('Nom de l\'entreprise')
                        ->required(),

                    TextInput::make('contact_name')
                        ->label('Nom du contact'),

                    TextInput::make('phone')
                        ->label('Téléphone')
                        ->tel(),

                    TextInput::make('fax')
                        ->label('Fax'),

                    TextInput::make('commission')
                        ->label('Commission')
                        ->numeric()
                        ->prefix('%')
                        ->default(0.00),

                    Textarea::make('address')
                        ->label('Adresse')
                        ->columnSpanFull(),

                    Textarea::make('notes')
                        ->label('Champs info libre')
                        ->columnSpanFull(),
                ])->columns(2)
        ]);
    }
}