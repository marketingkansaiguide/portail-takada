<?php

namespace App\Filament\Resources\TransportProducts;

use App\Filament\Resources\TransportProducts\Pages;
use App\Models\Product;
use BackedEnum;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransportProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';


    public static function getNavigationLabel(): string
    {
        return __('Transports');
    }

    public static function getModelLabel(): string
    {
        return __('Billet de transport');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Transports');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('product_type', 'transport');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()->schema([
                    Section::make(__('Présentation du Billet / Transport'))
                        ->description(__('Renseignez le nom du billet (ex: Billet Shinkansen à l\'unité, Pass Bus...) et la description.'))
                        ->schema([
                            TextInput::make('name')
                                ->label(__('Nom du Billet / Prestation'))
                                ->placeholder(__('Ex: Billet Shinkansen (Train) à l\'unité'))
                                ->required()
                                ->columnSpanFull(),

                            Textarea::make('description')
                                ->label(__('Description / Consignes'))
                                ->placeholder(__('Précisions sur les réservations de trains ou bus...'))
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),

                    Section::make(__('Classes & Options de transport'))
                        ->description(__('Définissez les classes ou options disponibles (ex: Voiture Standard, Green Car, Gran Class, Siege Réservé...).'))
                        ->schema([
                            Repeater::make('productOptions')
                                ->relationship()
                                ->hiddenLabel()
                                ->addActionLabel(__('Ajouter une option / classe'))
                                ->itemLabel(fn (array $state): ?string => isset($state['name']) ? $state['name'] . ' (+' . ($state['price_modifier'] ?? 0) . ' ¥)' : __('Nouvelle classe'))
                                ->collapsible()
                                ->columns(3)
                                ->schema([
                                    TextInput::make('name')
                                        ->label(__('Nom de la classe / Option'))
                                        ->placeholder(__('Ex: Green Car (Voiture 1ère classe)'))
                                        ->required(),

                                    TextInput::make('price_modifier')
                                        ->label(__('Supplément (¥)'))
                                        ->numeric()
                                        ->default(0)
                                        ->required(),

                                    Select::make('billing_type')
                                        ->label(__('Facturation'))
                                        ->options([
                                            'per_pax' => __('Par Pax'),
                                            'per_booking' => __('Fixe par dossier'),
                                        ])
                                        ->default('per_pax')
                                        ->required(),
                                ])
                        ]),

                    Section::make(__('Frais de Réservation / Émission'))
                        ->description(__('Grille tarifaire des frais fixes/frais d\'émission Takada.'))
                        ->schema([
                            Repeater::make('productPeriods')
                                ->relationship()
                                ->hiddenLabel()
                                ->collapsible()
                                ->addActionLabel(__('Définir une période de tarif'))
                                ->schema([
                                    TextInput::make('name')
                                        ->label(__('Nom de la saison'))
                                        ->default('Toute l\'année')
                                        ->required(),

                                    Repeater::make('productPrices')
                                        ->relationship()
                                        ->label(__('Frais fixes par tranche de pax'))
                                        ->schema([
                                            TextInput::make('min_pax')->label(__('Pax Min'))->numeric()->default(1),
                                            TextInput::make('max_pax')->label(__('Pax Max'))->numeric()->default(99),
                                            TextInput::make('price')
                                                ->label(__('Frais de dossier / émission (¥)'))
                                                ->numeric()
                                                ->default(0)
                                                ->required(),
                                        ])->columns(3)
                                ])
                        ]),
                ])->columnSpan(['lg' => 2]),

                Group::make()->schema([
                    Section::make(__('Configuration Métier'))
                        ->schema([
                            Select::make('category_id')
                                ->relationship('category', 'name')
                                ->label(__('Catégorie'))
                                ->required()
                                ->searchable()
                                ->preload(),

                            Toggle::make('is_on_demand')
                                ->label(__('Toujours sur devis'))
                                ->default(true)
                                ->disabled()
                                ->dehydrated(),

                            Toggle::make('is_public')
                                ->label(__('Visible sur le catalogue B2B'))
                                ->default(true),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Billet / Transport'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('Catégorie'))
                    ->badge(),

                Tables\Columns\IconColumn::make('is_on_demand')
                    ->label(__('Sur Devis'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Créé le'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportProducts::route('/'),
            'create' => Pages\CreateTransportProduct::route('/create'),
            'edit' => Pages\EditTransportProduct::route('/{record}/edit'),
        ];
    }
}