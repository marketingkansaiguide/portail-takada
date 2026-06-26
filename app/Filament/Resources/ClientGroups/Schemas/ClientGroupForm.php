<?php

namespace App\Filament\Resources\ClientGroups\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Configuration du Groupe')
                ->description('Définissez les règles tarifaires de base pour ce groupe')
                ->schema([
                    TextInput::make('name')
                        ->label('Nom du Groupe (ex: VIP, Standard)')
                        ->required()
                        ->unique(ignoreRecord: true),

                    TextInput::make('folder_fee')
                        ->label('Frais de dossier fixes')
                        ->numeric()
                        ->suffix('¥')
                        ->default(0)
                        ->helperText('Ces frais s\'appliqueront dès la première prestation validée.'),
                ])->columns(2)
        ]);
    }
}