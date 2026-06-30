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
            Section::make(__('Informations Fournisseur'))
                ->description(__('Coordonnées et paramètres de commission'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Nom de l\'entreprise'))
                        ->required(),

                    TextInput::make('contact_name')
                        ->label(__('Nom du contact')),

                    TextInput::make('phone')
                        ->label(__('Téléphone'))
                        ->tel(),

                    TextInput::make('fax')
                        ->label(__('Fax')),

                    TextInput::make('commission')
                        ->label(__('Commission'))
                        ->numeric()
                        ->prefix('%')
                        ->default(0.00),

                    Textarea::make('address')
                        ->label(__('Adresse'))
                        ->columnSpanFull(),

                    Textarea::make('notes')
                        ->label(__('Champs info libre'))
                        ->columnSpanFull(),
                ])->columns(2)
        ]);
    }
}