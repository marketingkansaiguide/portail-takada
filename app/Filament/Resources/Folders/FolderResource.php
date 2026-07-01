<?php

namespace App\Filament\Resources\Folders;

use App\Filament\Resources\Folders\Pages;
use App\Filament\Resources\Folders\FolderResource\RelationManagers; 
use App\Models\Folder;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action; 
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden; 
use Filament\Forms\Components\Placeholder; 
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

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

    // 💡 Retrait du typage strict ici
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

    // 💡 Retrait du typage strict ici
    public static function updateFolderTotal($set, $get)
    {
        $items = $get('folderItems') ?? [];
        $total = 0;
        foreach ($items as $item) {
            $total += (float) ($item['total_price'] ?? 0);
        }
        $fee = (float) ($get('folder_fee') ?? 0);
        $set('total_price', $total + $fee);
    }

    // 💡 Retrait du typage strict ici pour accepter la Closure de notre répéteur d'options
    public static function updateItemPrices($set, $get)
    {
        $productId = $get('product_id');
        $serviceDate = $get('service_date');
        $itemQuantity = (int) ($get('quantity') ?? 1);
        $selectedOptions = $get('selected_options') ?? [];

        if (!$productId) {
            return;
        }

        $product = \App\Models\Product::find($productId);
        if (!$product) return;

        $basePrice = 0;
        if ($serviceDate) {
            $date = Carbon::parse($serviceDate);
            $period = \App\Models\ProductPeriod::where('product_id', $productId)
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->first();

            if ($period) {
                $priceRow = \App\Models\ProductPrice::where('product_period_id', $period->id)
                    ->where('min_pax', '<=', $itemQuantity)
                    ->where('max_pax', '>=', $itemQuantity)
                    ->first();
                if ($priceRow) {
                    $basePrice = (float) $priceRow->price;
                }
            }
        }

        $perPaxOptionsTotal = 0;
        $fixedOptionsTotal = 0;

        if (is_array($selectedOptions)) {
            foreach ($selectedOptions as $optData) {
                if (empty($optData['product_option_id'])) continue;
                $option = \App\Models\ProductOption::find($optData['product_option_id']);
                if ($option) {
                    $mod = (float) $option->price_modifier;
                    if ($option->billing_type === 'per_pax') {
                        $perPaxOptionsTotal += $mod;
                    } elseif ($option->billing_type === 'per_booking') {
                        $fixedOptionsTotal += $mod;
                    } elseif ($option->billing_type === 'manual') {
                        $optQty = (int) ($optData['quantity'] ?? 1);
                        $fixedOptionsTotal += ($mod * $optQty);
                    }
                }
            }
        }

        $legacyOptionId = $get('product_option_id');
        if ($legacyOptionId && empty($selectedOptions)) {
            $option = \App\Models\ProductOption::find($legacyOptionId);
            if ($option) {
                $mod = (float) $option->price_modifier;
                if ($option->billing_type === 'per_pax') $perPaxOptionsTotal += $mod;
                elseif ($option->billing_type === 'per_booking') $fixedOptionsTotal += $mod;
            }
        }

        $unitPrice = $basePrice + $perPaxOptionsTotal;
        $totalPrice = ($unitPrice * $itemQuantity) + $fixedOptionsTotal;

        $set('unit_price', $unitPrice);
        $set('total_price', $totalPrice);

        $folderItems = $get('../../folderItems') ?? [];
        $globalTotal = 0;
        foreach ($folderItems as $item) {
            $globalTotal += (float) ($item['total_price'] ?? 0);
        }
        $fee = (float) ($get('../../folder_fee') ?? 0);
        $set('../../total_price', $globalTotal + $fee);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()->schema([
                    Section::make(__('Informations Générales'))
                        ->columns(4)
                        ->schema([
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

                            TextInput::make('pax_adults')
                                ->label(__('Composition : Adultes'))
                                ->readOnly()
                                ->dehydrated()
                                ->default(0),

                            TextInput::make('pax_children')
                                ->label(__('Composition : Enfants'))
                                ->readOnly()
                                ->dehydrated()
                                ->default(0),

                            Select::make('ticket_dispatch_method')
                                ->label(__('Envoi de la billetterie'))
                                ->options([
                                    'hotel' => __('Hôtel'),
                                    'guide' => __('Guide'),
                                    'autre' => __('Autre'),
                                ])
                                ->live() 
                                ->required(),

                            TextInput::make('ticket_dispatch_other')
                                ->label(__('Précisez le lieu d\'envoi'))
                                ->placeholder('Ex: Agence locale, Aéroport...')
                                ->required()
                                ->visible(fn (Get $get) => $get('ticket_dispatch_method') === 'autre'),

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
                                ->columns(3) 
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
                                ->minDate(fn (Get $get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null)
                                ->maxDate(fn (Get $get) => $get('end_date') ? Carbon::parse($get('end_date'))->endOfDay() : null),

                            Textarea::make('first_hotel_address')
                                ->label(__('Adresse du premier hôtel'))
                                ->placeholder(__('Adresse complète pour l\'envoi de billetterie / wifi...'))
                                ->rows(1),
                        ]),
                ])->columnSpan(['lg' => 2]),

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
                                    ->minDate(fn (Get $get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null),
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
                                    ->default(0)
                                    ->afterStateUpdated(fn ($set, $get) => self::updateFolderTotal($set, $get)),

                                Hidden::make('total_price')
                                    ->default(0),

                                Placeholder::make('total_price_display')
                                    ->label(__('Montant total (¥)'))
                                    ->content(function (Get $get, Set $set) {
                                        $items = $get('folderItems') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += (float) ($item['total_price'] ?? 0);
                                        }
                                        $fee = (float) ($get('folder_fee') ?? 0);
                                        $finalTotal = $total + $fee;
                                        
                                        $set('total_price', $finalTotal);
                                        
                                        return number_format($finalTotal, 0, '.', ' ') . ' ¥';
                                    }),
                            ])->columns(2),
                        ]),
                ])->columnSpan(['lg' => 1]),

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
                                            ->maxDate(fn (Get $get) => $get('../../start_date') ? Carbon::parse($get('../../start_date'))->startOfDay() : now()->endOfDay())
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
                                        ->placeholder('Ex: Fauteuil roulant, difficulty marches... Laissez vide si RAS.')
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
                                ->afterStateUpdated(fn ($set, $get) => self::updateFolderTotal($set, $get))
                                ->itemLabel(function (array $state) {
                                    if (!isset($state['product_id'])) return __('Nouvelle ligne de prestation');
                                    
                                    $productName = \App\Models\Product::find($state['product_id'])?->name ?? __('Produit inconnu');
                                    $date = !empty($state['service_date']) ? Carbon::parse($state['service_date'])->format('d/m/Y') : '---';
                                    $quantity = $state['quantity'] ?? 1;

                                    $optionName = __('Sans option');
                                    if (!empty($state['selected_options']) && is_array($state['selected_options'])) {
                                        $names = [];
                                        foreach ($state['selected_options'] as $opt) {
                                            if (!empty($opt['product_option_id'])) {
                                                $names[] = \App\Models\ProductOption::find($opt['product_option_id'])?->name;
                                            }
                                        }
                                        $names = array_filter($names);
                                        if (count($names) > 0) $optionName = implode(', ', $names);
                                    } elseif (!empty($state['product_option_id'])) {
                                        $optionName = \App\Models\ProductOption::find($state['product_option_id'])?->name ?? __('Sans option');
                                    }

                                    $statusModel = !empty($state['item_status_id']) ? \App\Models\ItemStatus::find($state['item_status_id']) : null;
                                    $statusName = $statusModel?->name ?? __('Aucun statut');
                                    $statusColor = $statusModel?->color ?? 'gray';

                                    $hexColor = match ($statusColor) {
                                        'success' => '#22c55e', 
                                        'warning' => '#f59e0b', 
                                        'danger' => '#ef4444',  
                                        'info' => '#3b82f6',    
                                        default => '#94a3b8',   
                                    };

                                    $mainText = "{$productName}  |  {$date}  |  {$optionName}  |  " . __('Qté : ') . $quantity;

                                    return new \Illuminate\Support\HtmlString("
                                        <span style='display: flex; justify-content: space-between; align-items: center; width: 100%;'>
                                            <span style='margin-right: 15px;'>{$mainText}</span>
                                            <span style='background-color: {$hexColor}; color: #ffffff; padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; box-shadow: inset 0 0 0 1px rgba(0,0,0,0.1);'>
                                                📌 {$statusName}
                                            </span>
                                        </span>
                                    ");
                                })
                                ->extraItemActions([
                                    Action::make('generateSupplierEmail')
                                        ->icon('heroicon-o-envelope')
                                        ->color('info')
                                        ->tooltip(__('Aperçu de l\'email fournisseur'))
                                        ->modalHeading(__('Mail Fournisseur'))
                                        ->modalSubmitActionLabel(__('Ouvrir dans Gmail'))
                                        ->modalCancelAction(false)
                                        ->form([
                                            TextInput::make('email_subject_preview')
                                                ->label(__('Objet de l\'e-mail'))
                                                ->readOnly(),
                                                
                                            Textarea::make('email_preview')
                                                ->label(__('Corps du message'))
                                                ->rows(15)
                                                ->readOnly()
                                        ])
                                        ->fillForm(function (array $arguments, \Filament\Forms\Components\Repeater $component): array {
                                            $state = $component->getState();
                                            $itemData = $state[$arguments['item']] ?? [];
                                            
                                            if (empty($itemData['id'])) {
                                                return [
                                                    'email_subject_preview' => '',
                                                    'email_preview' => __('Veuillez sauvegarder le dossier (Bouton "Enregistrer") au moins une fois pour cette ligne.')
                                                ];
                                            }
                                            
                                            $item = \App\Models\FolderItem::with(['product', 'folder.folderPassengers', 'productOption'])->find($itemData['id']);
                                            
                                            if ($item) {
                                                $item->quantity = $itemData['quantity'] ?? $item->quantity;
                                                if (!empty($itemData['service_date'])) {
                                                    $item->service_date = Carbon::parse($itemData['service_date']);
                                                }
                                                $item->selected_options = $itemData['selected_options'] ?? $item->selected_options ?? [];
                                                $item->custom_values = $itemData['custom_values'] ?? $item->custom_values ?? [];
                                                
                                                return [
                                                    'email_subject_preview' => $item->parseSupplierEmailSubject(),
                                                    'email_preview' => $item->parseSupplierEmail()
                                                ];
                                            }
                                            
                                            return [
                                                'email_subject_preview' => '',
                                                'email_preview' => __('Erreur lors du chargement de la prestation.')
                                            ];
                                        })
                                        ->action(function (array $data, array $arguments, \Filament\Forms\Components\Repeater $component) {
                                            $state = $component->getState();
                                            $itemData = $state[$arguments['item']] ?? [];
                                            
                                            $item = \App\Models\FolderItem::with(['product.supplier', 'folder'])->find($itemData['id']);
                                            if (!$item) return;

                                            $supplierEmail = ($item->product && $item->product->supplier) 
                                                ? $item->product->supplier->email 
                                                : '';

                                            $subject = $data['email_subject_preview'] ?? 'ご予約依頼';
                                            $body = $data['email_preview'] ?? '';

                                            $gmailUrl = "https://mail.google.com/mail/?view=cm&fs=1"
                                                . "&to=" . urlencode($supplierEmail)
                                                . "&su=" . urlencode($subject)
                                                . "&body=" . urlencode($body);

                                            $component->getLivewire()->js("window.open('{$gmailUrl}', '_blank')");
                                        })
                                ])
                                ->schema([
                                    
                                    Group::make()->schema([
                                        Select::make('product_id')
                                            ->relationship('product', 'name')
                                            ->label(__('Produit / Activité'))
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($set, $get, $state, $old) {
                                                if ($state !== $old) {
                                                    $set('selected_options', []); 
                                                    $set('custom_values', []); 
                                                }
                                                self::updateItemPrices($set, $get);
                                            })
                                            ->columnSpan(4),

                                        Select::make('item_status_id')
                                            ->relationship('itemStatus', 'name')
                                            ->label(__('Statut'))
                                            ->preload()
                                            ->searchable()
                                            ->live()
                                            ->default(fn () => \App\Models\ItemStatus::firstOrCreate(
                                                ['name' => 'En attente de validation'],
                                                ['color' => 'warning']
                                            )->id)
                                            ->columnSpan(2),
                                    ])->columns(6),

                                    Group::make()->schema([
                                        DatePicker::make('service_date')
                                            ->label(__('Date'))
                                            ->required()
                                            ->live()
                                            ->minDate(fn ($get) => $get('../../start_date') ? Carbon::parse($get('../../start_date'))->startOfDay() : null)
                                            ->maxDate(fn ($get) => $get('../../end_date') ? Carbon::parse($get('../../end_date'))->endOfDay() : null)
                                            ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get)),

                                        TextInput::make('quantity')
                                            ->label(__('Total Pax'))
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get)),

                                        TextInput::make('unit_price')
                                            ->label(__('Prix Unit. (¥)'))
                                            ->numeric()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                $itemQuantity = (int) ($get('quantity') ?? 1);
                                                $selectedOptions = $get('selected_options') ?? [];
                                                $fixedOptionsTotal = 0;

                                                if (is_array($selectedOptions)) {
                                                    foreach ($selectedOptions as $optData) {
                                                        if (empty($optData['product_option_id'])) continue;
                                                        $option = \App\Models\ProductOption::find($optData['product_option_id']);
                                                        if ($option) {
                                                            $mod = (float) $option->price_modifier;
                                                            if ($option->billing_type === 'per_booking') {
                                                                $fixedOptionsTotal += $mod;
                                                            } elseif ($option->billing_type === 'manual') {
                                                                $optQty = (int) ($optData['quantity'] ?? 1);
                                                                $fixedOptionsTotal += ($mod * $optQty);
                                                            }
                                                        }
                                                    }
                                                }
                                                $totalPrice = (((float) $state) * $itemQuantity) + $fixedOptionsTotal;
                                                $set('total_price', $totalPrice);
                                            }),

                                        TextInput::make('total_price')
                                            ->label(__('Total Net (¥)'))
                                            ->numeric()
                                            ->readOnly() 
                                            ->dehydrated()
                                            ->required(),
                                    ])->columns(4),

                                    Repeater::make('selected_options')
                                        ->label(__('Options Sélectionnées'))
                                        ->addActionLabel(__('Ajouter une option tarifaire'))
                                        ->defaultItems(0)
                                        ->live()
                                        ->afterStateUpdated(function ($set, $get) {
                                            self::updateItemPrices($set, $get);
                                        })
                                        ->schema([
                                            Select::make('product_option_id')
                                                ->label(__('Option proposée'))
                                                ->options(function ($get) {
                                                    $productId = $get('../../product_id');
                                                    if (!$productId) return [];
                                                    return \App\Models\ProductOption::where('product_id', $productId)->pluck('name', 'id');
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->live()
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $set('product_option_id', $state);
                                                    $parentSet = function($k, $v) use ($set) { $set('../../'.$k, $v); };
                                                    $parentGet = function($k) use ($get) { return $get('../../'.$k); };
                                                    self::updateItemPrices($parentSet, $parentGet);
                                                }),

                                            TextInput::make('quantity')
                                                ->label(__('Qté option'))
                                                ->numeric()
                                                ->default(1)
                                                ->live()
                                                ->visible(function ($get) {
                                                    $optionId = $get('product_option_id');
                                                    if (!$optionId) return false;
                                                    $opt = \App\Models\ProductOption::find($optionId);
                                                    return $opt && $opt->billing_type === 'manual';
                                                })
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $set('quantity', $state);
                                                    $parentSet = function($k, $v) use ($set) { $set('../../'.$k, $v); };
                                                    $parentGet = function($k) use ($get) { return $get('../../'.$k); };
                                                    self::updateItemPrices($parentSet, $parentGet);
                                                }),
                                        ])
                                        ->columns(2),

                                    Group::make()
                                        ->statePath('custom_values')
                                        ->schema(function ($get) {
                                            $productId = $get('product_id');
                                            if (!$productId) return [];

                                            $product = \App\Models\Product::find($productId);
                                            if (!$product || empty($product->custom_field_definitions)) return [];

                                            $fields = [];
                                            foreach ($product->custom_field_definitions as $def) {
                                                $type = $def['type'] ?? 'text';
                                                $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                                                $label = $def['name'] ?? 'Information';
                                                $placeholder = $def['placeholder'] ?? '';
                                                $isRequired = $def['is_required'] ?? false;
                                                $perPax = $def['is_per_passenger'] ?? false;

                                                if ($perPax) {
                                                    $label .= ' (' . __('Par passager') . ')';
                                                }

                                                $field = match ($type) {
                                                    'textarea' => Textarea::make($key)->label($label)->placeholder($placeholder)->rows(2),
                                                    'number' => TextInput::make($key)->numeric()->label($label)->placeholder($placeholder),
                                                    'date' => DatePicker::make($key)->label($label),
                                                    'toggle' => Toggle::make($key)->label($label)->inline(false),
                                                    'select' => TextInput::make($key)
                                                        ->label($label)
                                                        ->placeholder($placeholder ?: __('Sélectionnez ou tapez librement...'))
                                                        ->datalist(function() use ($def) {
                                                            return $def['choices'] ?? [];
                                                        }),
                                                    default => TextInput::make($key)->label($label)->placeholder($placeholder),
                                                };

                                                if ($isRequired && $type !== 'toggle') {
                                                    $field->required();
                                                }

                                                $fields[] = $field;
                                            }

                                            return [
                                                Section::make(__('Informations spécifiques (Réponses)'))
                                                    ->description(__('Remplissez les critères requis pour cette prestation. Vous pouvez ignorer les suggestions et taper un texte libre.'))
                                                    ->schema($fields)
                                                    ->columns(2)
                                            ];
                                        }),
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\HistoriesRelationManager::class,
        ];
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