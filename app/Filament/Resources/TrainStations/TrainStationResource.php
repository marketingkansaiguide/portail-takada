<?php

namespace App\Filament\Resources\TrainStations;

use App\Filament\Resources\TrainStations\Pages;
use App\Models\TrainStation;
use BackedEnum;
use UnitEnum;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TrainStationResource extends Resource
{
    protected static ?string $model = TrainStation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static UnitEnum|string|null $navigationGroup = 'Configuration';

    public static function getNavigationLabel(): string
    {
        return __('Stations de Train');
    }

    public static function getModelLabel(): string
    {
        return __('Station de Train');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Stations de Train');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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

                TextInput::make('category')
                    ->label(__('Catégorie'))
                    ->maxLength(255),

                TextInput::make('prefecture')
                    ->label(__('Préfecture'))
                    ->maxLength(255),

                TextInput::make('google_maps_url')
                    ->label(__('Lien Google Maps'))
                    ->url()
                    ->placeholder('https://maps.google.com/...')
                    ->maxLength(500)
                    ->columnSpanFull(),

                Textarea::make('address')
                    ->label(__('Adresse'))
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_en')
                    ->label(__('Nom (EN)'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name_ja')
                    ->label(__('Nom (JA)'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('prefecture')
                    ->label(__('Préfecture'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->label(__('Catégorie'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('google_maps_url')
                    ->label(__('Google Maps'))
                    ->formatStateUsing(fn ($state) => $state ? '📍 Voir sur la carte' : '---')
                    ->url(fn ($record) => $record->google_maps_url, true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
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