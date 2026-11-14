<?php

namespace App\Filament\Resources\TransportFares;

use App\Filament\Resources\TransportFares\Pages;
use App\Models\BusStation;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\TrainStation;
use App\Models\TransportFare;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/** Grille tarifaire des billets de train / bus : prix indicatifs affichés aux agences. */
class TransportFareResource extends Resource
{
    protected static ?string $model = TransportFare::class;
    protected static bool $isGloballySearchable = false;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-yen';
    protected static \UnitEnum|string|null $navigationGroup = 'Bases de données';

    public static function getNavigationLabel(): string
    {
        return __('Tarifs billets train');
    }

    public static function getModelLabel(): string
    {
        return __('Tarif billet');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tarifs billets train');
    }

    /** @return array<int, string> options « classe » du produit billet */
    public static function classOptions(): array
    {
        $productIds = Product::where('product_type', 'transport')->pluck('id');

        return ProductOption::whereIn('product_id', $productIds)->orderBy('id')->pluck('name', 'id')->all();
    }

    private static function stationSelect(string $field, string $label): Select
    {
        return Select::make($field)
            ->label($label)
            ->required()
            ->searchable()
            ->helperText(__('Nom exact de la gare dans le portail (celui que choisit l’agence).'))
            ->getSearchResultsUsing(function (string $search): array {
                $trains = TrainStation::where('name_en', 'like', "%{$search}%")->orWhere('name_ja', 'like', "%{$search}%")
                    ->orderByDesc('importance_score')->limit(15)->get()
                    ->mapWithKeys(fn ($s) => [$s->name_en => "🚆 {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : '') . ($s->city ? " - {$s->city}" : '')]);
                $buses = BusStation::where('name_en', 'like', "%{$search}%")->limit(5)->get()
                    ->mapWithKeys(fn ($s) => [$s->name_en => "🚌 {$s->name_en}"]);

                return $trains->union($buses)->all();
            })
            ->getOptionLabelUsing(fn ($value) => $value);
    }

    public static function form(Schema $schema): Schema
    {
        $yen = fn (string $field, string $label) => TextInput::make($field)->label($label)->numeric()->minValue(0)->suffix('¥');

        return $schema->components([
            Section::make(__('Trajet'))->schema([
                Group::make()->schema([
                    self::stationSelect('departure_station', __('Gare de départ')),
                    self::stationSelect('arrival_station', __('Gare d’arrivée')),
                ])->columns(2),
                Group::make()->schema([
                    TextInput::make('train_name')
                        ->label(__('Train'))
                        ->placeholder(__('Nozomi, Hida… (vide = tous les trains)'))
                        ->helperText(__('Reconnu dans le n° de train saisi par l’agence (« Hikari 502 »). Sans train précisé, le plus cher du trajet est pris.'))
                        ->maxLength(100),
                    Select::make('product_option_id')
                        ->label(__('Classe'))
                        ->options(fn () => self::classOptions())
                        ->required(),
                    Toggle::make('both_directions')->label(__('Valable dans les deux sens'))->default(true)->inline(false),
                ])->columns(3),
            ]),
            Section::make(__('Prix adulte par personne (billet complet : billet de base + supplément)'))->schema([
                Select::make('pricing_mode')
                    ->label(__('Mode tarifaire'))
                    ->options([TransportFare::MODE_SEASONAL => __('Saisons JR (4 prix)'), TransportFare::MODE_FLAT => __('Prix unique toute l’année')])
                    ->default(TransportFare::MODE_SEASONAL)
                    ->live()
                    ->required(),
                Group::make()->schema([
                    $yen('price_low', __('Saison basse')),
                    $yen('price_normal', __('Saison normale')),
                    $yen('price_high', __('Saison haute')),
                    $yen('price_peak', __('Saison très haute')),
                ])->columns(4)->visible(fn (Get $get) => $get('pricing_mode') !== TransportFare::MODE_FLAT),
                $yen('price_flat', __('Prix unique'))->visible(fn (Get $get) => $get('pricing_mode') === TransportFare::MODE_FLAT),
                $yen('child_price', __('Prix enfant (6-11 ans)'))
                    ->helperText(__('Vide = moitié du prix adulte, arrondie aux 10 ¥ inférieurs.')),
                Group::make()->schema([
                    DatePicker::make('valid_from')->label(__('Valable du')),
                    DatePicker::make('valid_to')->label(__('Valable au')),
                    Toggle::make('is_active')->label(__('Actif'))->default(true)->inline(false),
                ])->columns(3),
                Textarea::make('notes')->label(__('Remarques'))->rows(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $yen = fn ($state) => $state ? number_format((int) $state, 0, '.', ' ') . ' ¥' : '—';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('departure_station')->label(__('Départ'))->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('arrival_station')->label(__('Arrivée'))->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('train_name')->label(__('Train'))->placeholder(__('tous'))->searchable(),
                Tables\Columns\TextColumn::make('productOption.name')->label(__('Classe'))->badge(),
                Tables\Columns\TextColumn::make('price_normal')->label(__('Normale'))
                    ->state(fn (TransportFare $r) => $r->pricing_mode === TransportFare::MODE_FLAT ? $r->price_flat : $r->price_normal)
                    ->formatStateUsing($yen)
                    ->description(fn (TransportFare $r) => $r->pricing_mode === TransportFare::MODE_FLAT ? __('prix unique') : null),
                Tables\Columns\TextColumn::make('price_low')->label(__('Basse'))->formatStateUsing($yen)->toggleable(),
                Tables\Columns\TextColumn::make('price_high')->label(__('Haute'))->formatStateUsing($yen)->toggleable(),
                Tables\Columns\TextColumn::make('price_peak')->label(__('Très haute'))->formatStateUsing($yen)->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label(__('Actif'))->boolean(),
                Tables\Columns\TextColumn::make('source')->label(__('Source'))->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label(__('Modifié le'))->dateTime('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_option_id')->label(__('Classe'))->options(fn () => self::classOptions()),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Actif')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('departure_station');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportFares::route('/'),
            'create' => Pages\CreateTransportFare::route('/create'),
            'edit' => Pages\EditTransportFare::route('/{record}/edit'),
        ];
    }
}
