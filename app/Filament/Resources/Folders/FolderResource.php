<?php

namespace App\Filament\Resources\Folders;

use App\Filament\Resources\Folders\Pages;
use App\Models\Folder;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FolderResource extends Resource
{
    protected static ?string $model = Folder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    public static function getNavigationLabel(): string
    {
        return __('Dossiers Clients');
    }

    public static function getModelLabel(): string
    {
        return __('Dossier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Dossiers Clients');
    }

    // 🔄 MOTEUR AUTOMATIQUE 1 : Recalculer le nombre d'adultes et d'enfants en temps réel
    public static function updatePassengerCount($set, $get)
    {
        $passengers = $get('folderPassengers') ?? [];
        $startDate = $get('start_date');
        $adults = 0;
        $children = 0;

        foreach ($passengers as $passenger) {
            if (!empty($passenger['birth_date'])) {
                $birthDate = Carbon::parse($passenger['birth_date']);
                $compareDate = $startDate ? Carbon::parse($startDate) : Carbon::now();
                if ($birthDate->diffInYears($compareDate) >= 18) {
                    $adults++;
                } else {
                    $children++;
                }
            }
        }

        $set('pax_adults', $adults);
        $set('pax_children', $children);
    }

    // 🔄 MOTEUR AUTOMATIQUE 2 : Charger automatiquement les tarifs officiels du catalogue
    public static function updateItemPrices($set, $get)
    {
        $productId = $get('product_id');
        $serviceDate = $get('service_date');
        $quantity = (int) ($get('quantity') ?? 1);
        $optionId = $get('product_option_id');

        if (!$productId || !$serviceDate) {
            return;
        }

        $product = \App\Models\Product::find($productId);
        if (!$product) return;

        // 1. Recherche de la saison correspondante à la date de la prestation
        $date = Carbon::parse($serviceDate);
        $period = \App\Models\ProductPeriod::where('product_id', $productId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        $basePrice = 0;
        if ($period) {
            // 2. Recherche du palier de prix selon le nombre de voyageurs (Pax)
            $priceRow = \App\Models\ProductPrice::where('product_period_id', $period->id)
                ->where('min_pax', '<=', $quantity)
                ->where('max_pax', '>=', $quantity)
                ->first();
            if ($priceRow) {
                $basePrice = $priceRow->price;
            }
        }

        // 3. Application du supplément de l'option choisie
        $optionModifier = 0;
        $isPerBooking = false;
        if ($optionId) {
            $option = \App\Models\ProductOption::find($optionId);
            if ($option) {
                $optionModifier = $option->price_modifier;
                if ($option->billing_type === 'per_booking') {
                    $isPerBooking = true;
                }
            }
        }

        // Calcul final unitaire et ligne
        $unitPrice = $basePrice + ($isPerBooking ? 0 : $optionModifier);
        $totalPrice = ($unitPrice * $quantity) + ($isPerBooking ? $optionModifier : 0);

        // Remplissage automatique des champs financiers
        $set('unit_price', $unitPrice);
        $set('total_price', $totalPrice);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                
                // 🏢 BLOC SUPÉRIEUR GAUCHE ULTRA-OPTIMISÉ (Prend 2/3 de la largeur)
                Group::make()->schema([
                    
                    Section::make(__('Informations Générales'))
                        ->columns(4)
                        ->schema([
                            // Ligne 1 : Les 4 identifiants majeurs
                            TextInput::make('folder_name')
                                ->label(__('Nom du dossier'))
                                ->placeholder('Ex: Circuit Hanami 2026')
                                ->required(),

                            TextInput::make('lead_traveler_name')
                                ->label(__('Pax Leader'))
                                ->placeholder('Ex: Jean Dupont')
                                ->required(),

                            TextInput::make('hotel_booking_name')
                                ->label(__('Nom réservation hôtel'))
                                ->placeholder(__('Si différent')),

                            Select::make('agency_id')
                                ->relationship('agency', 'name')
                                ->label(__('Agence émettrice'))
                                ->required()
                                ->searchable()
                                ->preload(),

                            // Ligne 2 : Données démographiques et logistique Billetterie
                            TextInput::make('pax_adults')
                                ->label(__('Composition : Adultes'))
                                ->disabled()
                                ->dehydrated()
                                ->default(0),

                            TextInput::make('pax_children')
                                ->label(__('Composition : Enfants'))
                                ->disabled()
                                ->dehydrated()
                                ->default(0),

                            // 🎯 NOUVEAUTÉ : Choix du canal d'envoi de la billetterie
                            Select::make('ticket_dispatch_method')
                                ->label(__('Envoi de la billetterie'))
                                ->options([
                                    'hotel' => __('Hôtel'),
                                    'guide' => __('Guide'),
                                    'autre' => __('Autre'),
                                ])
                                ->live() // Déclenche instantanément l'affichage du champ texte ci-dessous
                                ->required(),

                            // 🎯 NOUVEAUTÉ CONDITIONNELLE : S'ouvre uniquement si "Autre" est sélectionné
                            TextInput::make('ticket_dispatch_other')
                                ->label(__('Précisez le lieu d\'envoi'))
                                ->placeholder('Ex: Agence locale, Aéroport...')
                                ->required()
                                ->visible(fn ($get) => $get('ticket_dispatch_method') === 'autre'),

                            // ⚡ OPTIMISATION VISUELLE : Le repeater des téléphones passe sur sa propre ligne de manière compacte
                            Repeater::make('contact_phones')
                                ->label(__('Numéros de téléphone de contact'))
                                ->addActionLabel(__('Ajouter un numéro de contact'))
                                ->schema([
                                    TextInput::make('phone')
                                        ->label('')
                                        ->tel()
                                        ->required()
                                        ->placeholder('+33 6...'),
                                ])
                                ->defaultItems(1)
                                ->columns(3) // Range les téléphones horizontalement 3 par 3 !
                                ->columnSpanFull(),
                        ]),

                    Section::make(__('Informations du Premier Hôtel'))
                        ->columns(3)
                        ->schema([
                            TextInput::make('first_hotel_name')
                                ->label(__('Nom du premier hôtel'))
                                ->placeholder('Ex: Hotel Gracery Shinjuku'),

                            DatePicker::make('first_hotel_check_in')
                                ->label(__('Date de check-in du 1er hôtel'))
                                ->live()
                                ->minDate(fn ($get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null)
                                ->maxDate(fn ($get) => $get('end_date') ? Carbon::parse($get('end_date'))->endOfDay() : null),

                            Textarea::make('first_hotel_address')
                                ->label(__('Adresse du premier hôtel'))
                                ->placeholder(__('Adresse complète pour l\'envoi de billetterie / wifi...'))
                                ->rows(1),
                        ]),
                ])->columnSpan(['lg' => 2]),

                // 🏢 BLOC SUPÉRIEUR DROIT CONDENSÉ (Prend 1/3 de la largeur)
                Group::make()->schema([
                    Section::make(__('Informations de Vol & Séjour au Japon'))
                        ->description(__('Renseignez les dates globales du séjour ainsi que le détail des vols.'))
                        ->schema([
                            Group::make()->schema([
                                DatePicker::make('start_date')
                                    ->label(__('Date d\'arrivée au Japon'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get)),

                                DatePicker::make('end_date')
                                    ->label(__('Date de départ'))
                                    ->required()
                                    ->live()
                                    ->minDate(fn ($get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null),
                            ])->columns(2),

                            Textarea::make('flight_info')
                                ->label(__('Informations de vols'))
                                ->placeholder('Ex: Vol AF276 Arrivée Haneda 10:30 / Vol Retour AF275...')
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),

                    Section::make(__('Statut & Facturation'))
                        ->schema([
                            Select::make('status')
                                ->label(__('Statut du dossier'))
                                ->options([
                                    'draft' => __('Brouillon'),
                                    'pending' => __('En attente de validation'),
                                    'confirmed' => __('Confirmé / Validé'),
                                    'completed' => __('Voyage terminé'),
                                    'cancelled' => __('Annulé'),
                                ])
                                ->default('draft')
                                ->required(),

                            Group::make()->schema([
                                TextInput::make('folder_fee')
                                    ->label(__('Frais de dossier (¥)'))
                                    ->numeric()
                                    ->live()
                                    ->default(0),

                                TextInput::make('total_price')
                                    ->label(__('Montant total (¥)'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->formatStateUsing(function ($get) {
                                        $items = $get('folderItems') ?? [];
                                        $itemsTotal = 0;
                                        foreach ($items as $item) {
                                            $itemsTotal += (int) ($item['total_price'] ?? 0);
                                        }
                                        $fee = (int) ($get('folder_fee') ?? 0);
                                        return $itemsTotal + $fee;
                                    }),
                            ])->columns(2),
                        ]),
                ])->columnSpan(['lg' => 1]),

                // 🏢 BLOC INFÉRIEUR LARGE
                Group::make()->schema([
                    Section::make(__('Liste des Voyageurs'))
                        ->description(__('Renseignez l\'identité, l\'âge et les contraintes médicales ou alimentaires de chaque passager.'))
                        ->schema([
                            Repeater::make('folderPassengers')
                                ->relationship()
                                ->label('')
                                ->addActionLabel(__('Ajouter un voyageur'))
                                ->collapsible()
                                ->collapsed()
                                ->live()
                                ->defaultItems(0)
                                ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get))
                                ->itemLabel(function (array $state): ?string {
                                    if (empty($state['last_name']) && empty($state['first_name'])) {
                                        return __('Nouveau voyageur');
                                    }
                                    $fullName = trim(mb_strtoupper($state['last_name'] ?? '') . ' ' . ($state['first_name'] ?? ''));
                                    $birthDate = !empty($state['birth_date']) ? Carbon::parse($state['birth_date'])->format('d/m/Y') : '---';
                                    $nationality = $state['nationality'] ?? '---';

                                    $label = "{$fullName}  |  {$birthDate}  |  {$nationality}";

                                    if (!empty($state['dietary_restrictions'])) {
                                        $label .= "  |  🚫 " . __('Allergies : ') . $state['dietary_restrictions'];
                                    }
                                    if (!empty($state['mobility_concerns'])) {
                                        $label .= "  |  ♿ " . __('Handicap : ') . $state['mobility_concerns'];
                                    }

                                    return $label;
                                })
                                ->schema([
                                    Group::make()->schema([
                                        TextInput::make('last_name')
                                            ->label(__('Nom de famille'))
                                            ->required(),
                                        
                                        TextInput::make('first_name')
                                            ->label(__('Prénom'))
                                            ->required(),
                                    ])->columns(2),

                                    Group::make()->schema([
                                        DatePicker::make('birth_date')
                                            ->label(__('Date de naissance'))
                                            ->required()
                                            ->live()
                                            ->maxDate(fn ($get) => $get('../../start_date') ? Carbon::parse($get('../../start_date'))->startOfDay() : now()->endOfDay())
                                            ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get)),

                                        TextInput::make('nationality')
                                            ->label(__('Nationalité'))
                                            ->placeholder('Ex: Française, Japonaise...')
                                            ->required(),
                                    ])->columns(2),

                                    Textarea::make('dietary_restrictions')
                                        ->label(__('Allergies / restrictions alimentaires'))
                                        ->placeholder('Ex: Allergie arachides, sans porc, végétarien... Laissez vide si RAS.')
                                        ->rows(2)
                                        ->columnSpanFull(),

                                    Textarea::make('mobility_concerns')
                                        ->label(__('Handicap / mobilité réduite'))
                                        ->placeholder('Ex: Fauteuil roulant, difficulté marches... Laissez vide si RAS.')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])
                        ]),

                    Section::make(__('Prestations commandées'))
                        ->description(__('Gérez les articles et options tarifaires liés à ce dossier.'))
                        ->schema([
                            Repeater::make('folderItems')
                                ->relationship()
                                ->label('')
                                ->addActionLabel(__('Ajouter une prestation au dossier'))
                                ->collapsible()
                                ->collapsed()
                                ->live()
                                ->defaultItems(0)
                                ->itemLabel(function (array $state): ?string {
                                    if (!isset($state['product_id'])) {
                                        return __('Nouvelle ligne de prestation');
                                    }
                                    $productName = \App\Models\Product::find($state['product_id'])?->name ?? __('Produit inconnu');
                                    $date = !empty($state['service_date']) ? Carbon::parse($state['service_date'])->format('d/m/Y') : '---';
                                    $optionName = !empty($state['product_option_id']) ? \App\Models\ProductOption::find($state['product_option_id'])?->name : __('Sans option');
                                    $quantity = $state['quantity'] ?? 1;

                                    return "{$productName}  |  {$date}  |  {$optionName}  |  " . __('Qté : ') . $quantity;
                                })
                                ->schema([
                                    Group::make()->schema([
                                        Select::make('product_id')
                                            ->relationship('product', 'name')
                                            ->label(__('Produit / Activité'))
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get)),

                                        Select::make('product_option_id')
                                            ->label(__('Option choisie'))
                                            ->options(function ($get) {
                                                $productId = $get('product_id');
                                                if (! $productId) return [];
                                                return \App\Models\ProductOption::where('product_id', $productId)
                                                    ->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get)),
                                    ])->columns(2),

                                    Group::make()->schema([
                                        DatePicker::make('service_date')
                                            ->label(__('Date de la prestation'))
                                            ->required()
                                            ->live()
                                            ->minDate(fn ($get) => $get('../../start_date') ? Carbon::parse($get('../../start_date'))->startOfDay() : null)
                                            ->maxDate(fn ($get) => $get('../../end_date') ? Carbon::parse($get('../../end_date'))->endOfDay() : null)
                                            ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get)),

                                        TextInput::make('quantity')
                                            ->label(__('Quantité / Voyageurs'))
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get)),
                                    ])->columns(2),

                                    Group::make()->schema([
                                        TextInput::make('unit_price')
                                            ->label(__('Prix unitaire (¥)'))
                                            ->numeric()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                $quantity = (int) ($get('quantity') ?? 1);
                                                $optionId = $get('product_option_id');
                                                $optionModifier = 0;
                                                $isPerBooking = false;
                                                if ($optionId) {
                                                    $option = \App\Models\ProductOption::find($optionId);
                                                    if ($option && $option->billing_type === 'per_booking') {
                                                        $optionModifier = $option->price_modifier;
                                                        $isPerBooking = true;
                                                    }
                                                }
                                                $totalPrice = (((int) $state) * $quantity) + ($isPerBooking ? $optionModifier : 0);
                                                $set('total_price', $totalPrice);
                                            }),

                                        TextInput::make('total_price')
                                            ->label(__('Prix total de la ligne (¥)'))
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->required(),
                                    ])->columns(2),

                                    KeyValue::make('custom_values')
                                        ->label(__('Informations spécifiques récoltées'))
                                        ->keyLabel(__('Critère requis'))
                                        ->valueLabel(__('Donnée voyageur'))
                                        ->addButtonLabel(__('Ajouter un détail')),
                                ])
                        ])
                ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label(__('Référence'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('agency.name')
                    ->label(__('Agence'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('folder_name')
                    ->label(__('Nom du dossier'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Statut'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_price')
                    ->label(__('Montant total'))
                    ->money('JPY')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFolders::route('/'),
            'create' => Pages\CreateFolder::route('/create'),
            'edit' => Pages\EditFolder::route('/{record}/edit'),
        ];
    }
}