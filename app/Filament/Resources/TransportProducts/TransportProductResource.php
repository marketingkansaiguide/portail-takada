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

                            // --- BLOC RÉPÉTEUR : TRAJETS (COMME CÔTÉ AGENCE) ---
                            Repeater::make('transport_routes')
                                ->label('Trajets du billet (Étapes)')
                                ->addActionLabel('Ajouter un trajet (Étape)')
                                ->itemLabel(fn (array $state): ?string => 
                                    (!empty($state['departure_station']) && !empty($state['arrival_station']))
                                        ? "{$state['departure_station']} ➔ {$state['arrival_station']}"
                                        : 'Nouveau trajet'
                                )
                                ->collapsible()
                                ->defaultItems(1)
                                ->columnSpanFull()
                                ->schema([
                                    Group::make()->schema([
                                        Select::make('departure_station')
                                            ->label('Gare / Station de départ')
                                            ->searchable()
                                            ->live()
                                            ->placeholder('Rechercher gare ou station...')
                                            ->getSearchResultsUsing(function (string $search): array {
                                                $trains = \App\Models\TrainStation::where(function($q) use ($search) {
                                                        $q->where('name_en', 'like', "%{$search}%")
                                                          ->orWhere('name_ja', 'like', "%{$search}%")
                                                          ->orWhere('name_kana', 'like', "%{$search}%")
                                                          ->orWhere('prefecture', 'like', "%{$search}%")
                                                          ->orWhere('city', 'like', "%{$search}%")
                                                          ->orWhere('aliases', 'like', "%{$search}%");
                                                    })
                                                    ->orderBy('importance_score', 'desc')
                                                    ->orderBy('name_en', 'asc')
                                                    ->limit(15)
                                                    ->get()
                                                    ->map(function ($s) {
                                                        $location = $s->city ?: $s->prefecture;
                                                        $locationSuffix = $location ? " - {$location}" : "";
                                                        return [
                                                            'id' => $s->name_en,
                                                            'label' => "🚆 {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : "") . $locationSuffix,
                                                            'score' => $s->importance_score ?? 10
                                                        ];
                                                    });

                                                $buses = \App\Models\BusStation::where('name_en', 'like', "%{$search}%")
                                                    ->orWhere('name_ja', 'like', "%{$search}%")
                                                    ->orWhere('address', 'like', "%{$search}%")
                                                    ->limit(10)
                                                    ->get()
                                                    ->map(function ($s) {
                                                        return [
                                                            'id' => $s->name_en,
                                                            'label' => "🚌 [Bus] {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : ""),
                                                            'score' => 90
                                                        ];
                                                    });

                                                return $trains->concat($buses)
                                                    ->sortByDesc('score')
                                                    ->take(15)
                                                    ->pluck('label', 'id')
                                                    ->toArray();
                                            })
                                            ->getOptionLabelUsing(function ($value) {
                                                $station = \App\Models\TrainStation::where('name_en', $value)->first() ?? \App\Models\BusStation::where('name_en', $value)->first();
                                                if ($station) {
                                                    $type = $station instanceof \App\Models\TrainStation ? '🚆' : '🚌 [Bus]';
                                                    $locationSuffix = ($station instanceof \App\Models\TrainStation && $station->city) ? " - {$station->city}" : "";
                                                    return "{$type} {$station->name_en}" . ($station->name_ja ? " ({$station->name_ja})" : "") . $locationSuffix;
                                                }
                                                return $value;
                                            })
                                            ->helperText(function ($get) {
                                                $stationName = $get('departure_station');
                                                if (!$stationName) return null;
                                                
                                                $station = \App\Models\TrainStation::where('name_en', $stationName)->first() 
                                                        ?? \App\Models\BusStation::where('name_en', $stationName)->first();
                                                        
                                                if ($station && !empty($station->google_maps_url)) {
                                                    return new \Illuminate\Support\HtmlString("<a href='{$station->google_maps_url}' target='_blank' style='color: #2563eb; text-decoration: underline; font-size: 0.8rem; display: flex; align-items: center; gap: 4px; margin-top: 4px;'><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor' style='width: 14px; height: 14px;'><path fill-rule='evenodd' d='M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.018.008.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z' clip-rule='evenodd' /></svg> Voir sur Google Maps</a>");
                                                }
                                                return null;
                                            }),

                                        Select::make('arrival_station')
                                            ->label('Gare / Station d\'arrivée')
                                            ->searchable()
                                            ->live()
                                            ->placeholder('Rechercher gare ou station...')
                                            ->getSearchResultsUsing(function (string $search): array {
                                                $trains = \App\Models\TrainStation::where(function($q) use ($search) {
                                                        $q->where('name_en', 'like', "%{$search}%")
                                                          ->orWhere('name_ja', 'like', "%{$search}%")
                                                          ->orWhere('name_kana', 'like', "%{$search}%")
                                                          ->orWhere('prefecture', 'like', "%{$search}%")
                                                          ->orWhere('city', 'like', "%{$search}%")
                                                          ->orWhere('aliases', 'like', "%{$search}%");
                                                    })
                                                    ->orderBy('importance_score', 'desc')
                                                    ->orderBy('name_en', 'asc')
                                                    ->limit(15)
                                                    ->get()
                                                    ->map(function ($s) {
                                                        $location = $s->city ?: $s->prefecture;
                                                        $locationSuffix = $location ? " - {$location}" : "";
                                                        return [
                                                            'id' => $s->name_en,
                                                            'label' => "🚆 {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : "") . $locationSuffix,
                                                            'score' => $s->importance_score ?? 10
                                                        ];
                                                    });

                                                $buses = \App\Models\BusStation::where('name_en', 'like', "%{$search}%")
                                                    ->orWhere('name_ja', 'like', "%{$search}%")
                                                    ->orWhere('address', 'like', "%{$search}%")
                                                    ->limit(10)
                                                    ->get()
                                                    ->map(function ($s) {
                                                        return [
                                                            'id' => $s->name_en,
                                                            'label' => "🚌 [Bus] {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : ""),
                                                            'score' => 90
                                                        ];
                                                    });

                                                return $trains->concat($buses)
                                                    ->sortByDesc('score')
                                                    ->take(15)
                                                    ->pluck('label', 'id')
                                                    ->toArray();
                                            })
                                            ->getOptionLabelUsing(function ($value) {
                                                $station = \App\Models\TrainStation::where('name_en', $value)->first() ?? \App\Models\BusStation::where('name_en', $value)->first();
                                                if ($station) {
                                                    $type = $station instanceof \App\Models\TrainStation ? '🚆' : '🚌 [Bus]';
                                                    $locationSuffix = ($station instanceof \App\Models\TrainStation && $station->city) ? " - {$station->city}" : "";
                                                    return "{$type} {$station->name_en}" . ($station->name_ja ? " ({$station->name_ja})" : "") . $locationSuffix;
                                                }
                                                return $value;
                                            })
                                            ->helperText(function ($get) {
                                                $stationName = $get('arrival_station');
                                                if (!$stationName) return null;
                                                
                                                $station = \App\Models\TrainStation::where('name_en', $stationName)->first() 
                                                        ?? \App\Models\BusStation::where('name_en', $stationName)->first();
                                                        
                                                if ($station && !empty($station->google_maps_url)) {
                                                    return new \Illuminate\Support\HtmlString("<a href='{$station->google_maps_url}' target='_blank' style='color: #2563eb; text-decoration: underline; font-size: 0.8rem; display: flex; align-items: center; gap: 4px; margin-top: 4px;'><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor' style='width: 14px; height: 14px;'><path fill-rule='evenodd' d='M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.018.008.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z' clip-rule='evenodd' /></svg> Voir sur Google Maps</a>");
                                                }
                                                return null;
                                            }),
                                    ])->columns(2),
                                ]),
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
                                ->columns(12)
                                ->schema([
                                    TextInput::make('group_name')
                                        ->label(__('Groupe (Ex: Classe)'))
                                        ->placeholder(__('Laissez vide si optionnel'))
                                        ->columnSpan(['default' => 12, 'md' => 4]),

                                    TextInput::make('name')
                                        ->label(__('Nom de l\'Option'))
                                        ->placeholder(__('Ex: Green Car'))
                                        ->required()
                                        ->columnSpan(['default' => 12, 'md' => 4]),

                                    TextInput::make('price_modifier')
                                        ->label(__('Supplément (¥)'))
                                        ->numeric()
                                        ->default(0)
                                        ->required()
                                        ->columnSpan(['default' => 12, 'md' => 4]),

                                    Select::make('billing_type')
                                        ->label(__('Facturation'))
                                        ->options([
                                            'per_pax' => __('Par Pax'),
                                            'per_booking' => __('Fixe par dossier'),
                                        ])
                                        ->default('per_pax')
                                        ->required()
                                        ->columnSpan(['default' => 12, 'md' => 6]),

                                    Toggle::make('is_required')
                                        ->label(__('Obligatoire'))
                                        ->default(false)
                                        ->inline(false)
                                        ->columnSpan(['default' => 12, 'md' => 6]),
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