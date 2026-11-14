<?php

namespace App\Filament\Resources\TransportFareSeasons;

use App\Filament\Resources\TransportFareSeasons\Pages;
use App\Models\TransportFareSeason;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/** Calendrier des saisons tarifaires JR : hors périodes saisies, la saison est « normale ». */
class TransportFareSeasonResource extends Resource
{
    protected static ?string $model = TransportFareSeason::class;
    protected static bool $isGloballySearchable = false;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static \UnitEnum|string|null $navigationGroup = 'Bases de données';

    public static function getNavigationLabel(): string
    {
        return __('Saisons tarifaires train');
    }

    public static function getModelLabel(): string
    {
        return __('Période de saison');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Saisons tarifaires train');
    }

    private static function seasons(): array
    {
        return collect(TransportFareSeason::SEASONS)->except('normal')->map(fn ($l) => __($l))->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('season')->label(__('Saison'))->options(self::seasons())->required(),
                TextInput::make('company')->label(__('Compagnie'))->placeholder(__('Toutes JR'))->maxLength(50),
                DatePicker::make('starts_on')->label(__('Du'))->required(),
                DatePicker::make('ends_on')->label(__('Au'))->required()->afterOrEqual('starts_on'),
                TextInput::make('notes')->label(__('Remarques'))->maxLength(255)->columnSpanFull(),
            ])->columns(2)->description(__('Hors de ces périodes, la saison normale s’applique. En cas de chevauchement, la saison la plus chère l’emporte.')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('season')->label(__('Saison'))->badge()
                    ->formatStateUsing(fn ($state) => __(TransportFareSeason::SEASONS[$state] ?? $state))
                    ->color(fn ($state) => match ($state) { 'peak' => 'danger', 'high' => 'warning', default => 'success' }),
                Tables\Columns\TextColumn::make('starts_on')->label(__('Du'))->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('ends_on')->label(__('Au'))->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('company')->label(__('Compagnie'))->placeholder(__('Toutes JR')),
                Tables\Columns\TextColumn::make('notes')->label(__('Remarques'))->limit(50),
            ])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('starts_on');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportFareSeasons::route('/'),
            'create' => Pages\CreateTransportFareSeason::route('/create'),
            'edit' => Pages\EditTransportFareSeason::route('/{record}/edit'),
        ];
    }
}
