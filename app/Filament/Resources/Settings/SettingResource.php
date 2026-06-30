<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;
    
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    public static function getNavigationLabel(): string
    {
        return __('Paramètres Généraux');
    }

    public static function getModelLabel(): string
    {
        return __('Paramètre');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Paramètres Généraux');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Politique d\'annulation globale'))
                    ->schema([
                        RichEditor::make('general_cancellation_policy')
                            ->label(__('Conditions Générales (Texte global)'))
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('Configuration'))
                    ->formatStateUsing(fn () => __('Paramètres globaux du site')),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Dernière modification'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return Setting::count() === 0;
    }
}