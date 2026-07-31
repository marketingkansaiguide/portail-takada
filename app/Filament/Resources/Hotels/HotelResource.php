<?php

namespace App\Filament\Resources\Hotels;

use App\Filament\Resources\Hotels\Pages;
use App\Models\Hotel;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

class HotelResource extends Resource
{
    protected static ?string $model = Hotel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static UnitEnum|string|null $navigationGroup = 'Configuration';

    public static function getNavigationLabel(): string
    {
        return __('Hôtels');
    }

    public static function getModelLabel(): string
    {
        return __('Hôtel');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Hôtels');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nom de l\'hôtel')
                ->required()
                ->maxLength(255),

            TextInput::make('phone')
                ->label('Numéro de téléphone')
                ->tel()
                ->placeholder('+81 ...')
                ->maxLength(50),

            TextInput::make('google_maps_url')
                ->label('Lien Google Maps')
                ->url()
                ->placeholder('https://maps.google.com/...')
                ->maxLength(500)
                ->columnSpanFull(),

            Textarea::make('address')
                ->label('Adresse de l\'hôtel')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom de l\'hôtel')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('address')
                    ->label('Adresse')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('google_maps_url')
                    ->label('Google Maps')
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
            'index' => Pages\ListHotels::route('/'),
            'create' => Pages\CreateHotel::route('/create'),
            'edit' => Pages\EditHotel::route('/{record}/edit'),
        ];
    }
}