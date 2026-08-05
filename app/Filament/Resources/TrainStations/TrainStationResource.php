<?php

namespace App\Filament\Resources\TrainStations;

use App\Filament\Resources\TrainStations\Pages;
use App\Models\TrainStation;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrainStationResource extends Resource
{
    protected static ?string $model = TrainStation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';
    protected static \UnitEnum|string|null $navigationGroup = 'Bases de données';    
    public static function getNavigationLabel(): string
    {
        return __('Gares (Trains)');
    }

    public static function getModelLabel(): string
    {
        return __('Gare');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Gares de Train');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations de la Gare')->schema([
                    Group::make()->schema([
                        TextInput::make('name_en')
                            ->label(__('Nom (Anglais)'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('name_ja')
                            ->label(__('Nom (Japonais)'))
                            ->maxLength(255),

                        TextInput::make('name_kana')
                            ->label(__('Nom (Kana)'))
                            ->maxLength(255),
                    ])->columns(3),

                    Group::make()->schema([
                        TextInput::make('prefecture')
                            ->label(__('Préfecture'))
                            ->maxLength(255),

                        TextInput::make('city')
                            ->label(__('Ville'))
                            ->maxLength(255),

                        TextInput::make('category')
                            ->label(__('Catégorie'))
                            ->default('station')
                            ->maxLength(255),
                    ])->columns(3),
                    
                    Group::make()->schema([
                        TextInput::make('importance_score')
                            ->label(__('Score de Pertinence (1-100)'))
                            ->numeric()
                            ->default(10)
                            ->helperText('Un score élevé affichera cette gare en premier dans les recherches (ex: 100 pour Osaka, 80 pour les hubs, 10 par défaut).'),
                            
                        TextInput::make('aliases')
                            ->label(__('Mots-clés / Alias'))
                            ->placeholder('Ex: Disney, USJ, Kansai Airport...')
                            ->helperText('Permet de trouver cette gare en tapant des mots-clés spécifiques.'),
                    ])->columns(2),

                    TextInput::make('address')
                        ->label(__('Adresse Complète'))
                        ->maxLength(500)
                        ->columnSpanFull(),

                    TextInput::make('google_maps_url')
                        ->label(__('Lien Google Maps'))
                        ->url()
                        ->placeholder('https://maps.google.com/...')
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_en')
                    ->label(__('Nom (EN)'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name_ja')
                    ->label(__('Nom (JA)'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('city')
                    ->label(__('Ville'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('prefecture')
                    ->label(__('Préfecture'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('importance_score')
                    ->label(__('Score'))
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 50 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('importance_score', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainStations::route('/'),
            'create' => Pages\CreateTrainStation::route('/create'),
            'edit' => Pages\EditTrainStation::route('/{record}/edit'),
        ];
    }
}