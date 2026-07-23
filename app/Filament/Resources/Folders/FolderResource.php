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
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn; 

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

    public static function updateFolderTotal($set, $get)
    {
        $items = $get('folderItems') ?? [];
        $totalSale = 0;
        $totalPurchase = 0;
        
        foreach ($items as $item) {
            $totalSale += (float) ($item['total_price'] ?? 0);
            $totalPurchase += (float) ($item['purchase_total_price'] ?? 0);
        }
        
        $fee = (float) ($get('folder_fee') ?? 0);
        
        $set('total_price', $totalSale + $fee);
    }

    public static function updateItemPrices($set, $get)
    {
        $productId = $get('product_id');
        $serviceDate = $get('service_date');
        $itemQuantity = (int) ($get('quantity') ?? 1);
        $selectedOptions = $get('selected_options') ?? [];

        // --- 1. CALCUL DU PRIX DE VENTE ---
        $basePrice = 0;
        if ($productId && $serviceDate) {
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
                        $optQty = (float) ($optData['quantity'] ?? 1);
                        $fixedOptionsTotal += ($mod * $optQty);
                    }
                }
            }
        }

        $unitPrice = (float) $basePrice + (float) $perPaxOptionsTotal;
        $totalPrice = ((float) $unitPrice * (float) $itemQuantity) + (float) $fixedOptionsTotal;

        $set('unit_price', $unitPrice);
        $set('total_price', $totalPrice);

        // --- 2. CALCUL DU PRIX D'ACHAT ---
        $purchaseUnitPrice = (float) ($get('purchase_unit_price') ?? 0);
        $set('purchase_total_price', $purchaseUnitPrice * $itemQuantity);
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
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn ($set) => $set('main_seller_id', null)),

                            Select::make('main_seller_id')
                                ->label(__('Vendeur principal'))
                                ->options(function ($get) {
                                    $agencyId = $get('agency_id');
                                    if (!$agencyId) return \App\Models\User::pluck('name', 'id');
                                    return \App\Models\User::where('agency_id', $agencyId)->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->nullable(),

                            TextInput::make('pax_adults')
                                ->label(__('Composition : Adultes'))
                                ->readOnly()
                                ->dehydrated(true)
                                ->mutateDehydratedStateUsing(function ($state, $get) {
                                    $passengers = $get('folderPassengers') ?? [];
                                    $startDate = $get('start_date');
                                    $adults = 0;
                                    foreach ($passengers as $passenger) {
                                        if (!empty($passenger['birth_date'])) {
                                            $birthDate = Carbon::parse($passenger['birth_date']);
                                            $compareDate = $startDate ? Carbon::parse($startDate) : Carbon::now();
                                            if ($birthDate->diffInYears($compareDate) >= 18) {
                                                $adults++;
                                            }
                                        }
                                    }
                                    return max(0, $adults);
                                })
                                ->default(0),

                            TextInput::make('pax_children')
                                ->label(__('Composition : Enfants'))
                                ->readOnly()
                                ->dehydrated(true)
                                ->mutateDehydratedStateUsing(function ($state, $get) {
                                    $passengers = $get('folderPassengers') ?? [];
                                    $startDate = $get('start_date');
                                    $children = 0;
                                    foreach ($passengers as $passenger) {
                                        if (!empty($passenger['birth_date'])) {
                                            $birthDate = Carbon::parse($passenger['birth_date']);
                                            $compareDate = $startDate ? Carbon::parse($startDate) : Carbon::now();
                                            if ($birthDate->diffInYears($compareDate) < 18) {
                                                $children++;
                                            }
                                        }
                                    }
                                    return max(0, $children);
                                })
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
                                ->visible(fn ($get) => $get('ticket_dispatch_method') === 'autre'),

                            Repeater::make('contact_phones')
                                ->label(__('Numéros de téléphone de contact'))
                                ->addActionLabel(__('Ajouter un numéro de contact'))
                                ->schema([
                                    TextInput::make('phone')
                                        ->hiddenLabel()
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
                                ->minDate(fn ($get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null)
                                ->maxDate(fn ($get) => $get('end_date') ? Carbon::parse($get('end_date'))->endOfDay() : null)
                                ->afterOrEqual('start_date')
                                ->beforeOrEqual('end_date')
                                ->validationMessages([
                                    'after_or_equal' => __('Doit être après ou le jour de l\'arrivée.'),
                                    'before_or_equal' => __('Doit être avant ou le jour du départ.'),
                                ]),

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
                                    ->beforeOrEqual('end_date')
                                    ->validationMessages([
                                        'before_or_equal' => __('L\'arrivée doit être avant ou le jour du départ.'),
                                    ])
                                    ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get)),

                                DatePicker::make('end_date')
                                    ->label(__('Date de départ'))
                                    ->required()
                                    ->live()
                                    ->afterOrEqual('start_date')
                                    ->validationMessages([
                                        'after_or_equal' => __('Le départ doit être après ou le jour de l\'arrivée.'),
                                    ])
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
                                    ->default(0)
                                    ->afterStateUpdated(fn ($set, $get) => self::updateFolderTotal($set, $get)),

                                Hidden::make('total_price')
                                    ->default(0)
                                    ->dehydrated(true)
                                    ->mutateDehydratedStateUsing(function ($state, $get) {
                                        $items = $get('folderItems') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += (float) ($item['total_price'] ?? 0);
                                        }
                                        $fee = (float) ($get('folder_fee') ?? 0);
                                        return $total + $fee;
                                    }),
                            ])->columns(1),

                            Group::make()->schema([
                                Placeholder::make('total_purchase_price_display')
                                    ->label(__('Coût d\'achat (Total)'))
                                    ->content(function ($get) {
                                        $items = $get('folderItems') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += (float) ($item['purchase_total_price'] ?? 0);
                                        }
                                        return number_format($total, 0, '.', ' ') . ' ¥';
                                    }),

                                Placeholder::make('total_price_display')
                                    ->label(__('Prix de vente (Total)'))
                                    ->content(function ($get) {
                                        $items = $get('folderItems') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += (float) ($item['total_price'] ?? 0);
                                        }
                                        $fee = (float) ($get('folder_fee') ?? 0);
                                        $finalTotal = $total + $fee;
                                        
                                        return number_format($finalTotal, 0, '.', ' ') . ' ¥';
                                    }),

                                Placeholder::make('margin_display')
                                    ->label(__('Marge Brute'))
                                    ->content(function ($get) {
                                        $items = $get('folderItems') ?? [];
                                        $totalSale = 0;
                                        $totalPurchase = 0;
                                        foreach ($items as $item) {
                                            $totalSale += (float) ($item['total_price'] ?? 0);
                                            $totalPurchase += (float) ($item['purchase_total_price'] ?? 0);
                                        }
                                        $fee = (float) ($get('folder_fee') ?? 0);
                                        $finalSale = $totalSale + $fee;

                                        $margin = $finalSale - $totalPurchase;
                                        $marginRate = $finalSale > 0 ? ($margin / $finalSale) * 100 : 0;

                                        $color = $margin >= 0 ? '#16a34a' : '#dc2626';

                                        return new \Illuminate\Support\HtmlString(
                                            "<span style='font-weight:bold; font-size:1.1rem; color: {$color};'>" .
                                            number_format($margin, 0, '.', ' ') . " ¥ (" . number_format($marginRate, 2, '.', '') . "%)" .
                                            "</span>"
                                        );
                                    }),
                            ])->columns(3),
                        ]),
                ])->columnSpan(['lg' => 1]),

                Group::make()->schema([
                    Section::make(__('Liste des Voyageurs'))
                        ->description(__('Renseignez l\'identité, l\'âge et les contraintes médicales ou alimentaires de chaque passager.'))
                        ->schema([
                            Repeater::make('folderPassengers')
                                ->relationship()
                                ->hiddenLabel() 
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
                                        ->placeholder('Ex: Fauteuil roulant, difficulty marches... Laissez vide si RAS.')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])
                        ]),

                    Section::make(__('Prestations commandées'))
                        ->description(__('Gérez les articles et options tarifaires liés à ce dossier. Pensez bien à sauvegarder le dossier après une modification !'))
                        ->schema([
                            Repeater::make('folderItems')
                                ->relationship()
                                ->hiddenLabel() 
                                ->addActionLabel(__('Ajouter une prestation au dossier'))
                                ->collapsible()
                                ->collapsed()
                                ->live()
                                ->defaultItems(0)
                                ->itemLabel(function (array $state) {
                                    if (!isset($state['product_id'])) return __('Nouvelle ligne de prestation');
                                    
                                    $productName = \App\Models\Product::find($state['product_id'])?->name ?? __('Produit inconnu');
                                    $date = !empty($state['service_date']) ? Carbon::parse($state['service_date'])->format('d/m/Y') : '---';
                                    $quantity = $state['quantity'] ?? 1;

                                    $supplierText = "";
                                    if (!empty($state['supplier_id'])) {
                                        $supplier = \App\Models\Supplier::find($state['supplier_id']);
                                        $supplierName = $supplier?->name ?? '';
                                        $supplierText = "  |  🏢 {$supplierName}";

                                        // 💡 UTILISATION DE requires_invoice AU LIEU DE furikomi
                                        if ($supplier && $supplier->requires_invoice && empty($state['invoice_received_at'])) {
                                            $supplierText .= "  |  <span style='color: #ef4444; font-weight: bold;'>⚠️ En attente de facture</span>";
                                        }
                                    } elseif (!empty($state['product_id'])) {
                                        $suppliersCount = \App\Models\ProductSupplier::where('product_id', $state['product_id'])->count();
                                        if ($suppliersCount > 1) {
                                            $supplierText = "  |  <span style='background-color: #fee2e2; color: #b91c1c; padding: 2px 8px; border-radius: 9999px; font-weight: bold; border: 1px solid #f87171;'>⚠️ SÉLECTIONNER UN FOURNISSEUR</span>";
                                        } elseif ($suppliersCount === 1) {
                                            $ps = \App\Models\ProductSupplier::where('product_id', $state['product_id'])->first();
                                            $supplier = \App\Models\Supplier::find($ps->supplier_id);
                                            $supplierName = $supplier?->name ?? '';
                                            $supplierText = "  |  🏢 {$supplierName}";

                                            // 💡 UTILISATION DE requires_invoice AU LIEU DE furikomi
                                            if ($supplier && $supplier->requires_invoice && empty($state['invoice_received_at'])) {
                                                $supplierText .= "  |  <span style='color: #ef4444; font-weight: bold;'>⚠️ En attente de facture</span>";
                                            }
                                        }
                                    }

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

                                    $mainText = "{$productName}  |  {$date}  |  {$optionName}  |  " . __('Qté : ') . $quantity . $supplierText;

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
                                            
                                            if (empty($itemData['supplier_id']) && !empty($itemData['product_id'])) {
                                                $suppliersCount = \App\Models\ProductSupplier::where('product_id', $itemData['product_id'])->count();
                                                if ($suppliersCount > 1) {
                                                    return [
                                                        'email_subject_preview' => '',
                                                        'email_preview' => __('Veuillez sélectionner un fournisseur avant de générer l\'email.')
                                                    ];
                                                }
                                            }

                                            if (empty($itemData['id'])) {
                                                return [
                                                    'email_subject_preview' => '',
                                                    'email_preview' => __('Veuillez sauvegarder le dossier (Bouton "Enregistrer") au moins une fois pour cette ligne.')
                                                ];
                                            }
                                            
                                            $item = \App\Models\FolderItem::with(['product', 'folder.folderPassengers', 'productOption'])->find($itemData['id']);
                                            
                                            if ($item) {
                                                $item->supplier_id = $itemData['supplier_id'] ?? $item->supplier_id;
                                                $item->product_id = $itemData['product_id'] ?? $item->product_id;
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
                                            
                                            if (empty($itemData['supplier_id']) && !empty($itemData['product_id'])) {
                                                $suppliersCount = \App\Models\ProductSupplier::where('product_id', $itemData['product_id'])->count();
                                                if ($suppliersCount > 1) {
                                                    \Filament\Notifications\Notification::make()
                                                        ->title(__('Fournisseur manquant'))
                                                        ->body(__('Dépliez la prestation et sélectionnez un fournisseur avant d\'envoyer l\'email.'))
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }
                                            }

                                            $item = \App\Models\FolderItem::with(['product', 'folder'])->find($itemData['id'] ?? null);
                                            if (!$item) return;

                                            $item->supplier_id = $itemData['supplier_id'] ?? $item->supplier_id;

                                            $targetSupplier = $item->getTargetSupplier();
                                            $supplierEmail = $targetSupplier ? $targetSupplier->email : '';

                                            $subject = $data['email_subject_preview'] ?? 'ご予約依頼';
                                            $body = $data['email_preview'] ?? '';

                                            $gmailUrl = "https://mail.google.com/mail/?view=cm&fs=1"
                                                . "&to=" . urlencode((string)$supplierEmail)
                                                . "&su=" . urlencode($subject)
                                                . "&body=" . urlencode($body);

                                            $component->getLivewire()->js("window.open('{$gmailUrl}', '_blank')");
                                        }),

                                    Action::make('generateGoogleSheetFax')
                                        ->icon('heroicon-o-document-text')
                                        ->color('success')
                                        ->tooltip(__('Ouvrir un nouveau FAX dans Google Sheets'))
                                        ->action(function (array $arguments, \Filament\Forms\Components\Repeater $component) {
                                            
                                            $googleDriveFolderId = '0AJNFhn85cg0OUk9PVA'; 

                                            $state = $component->getState();
                                            $itemData = $state[$arguments['item']] ?? [];
                                            
                                            if (empty($itemData['supplier_id']) && !empty($itemData['product_id'])) {
                                                $suppliersCount = \App\Models\ProductSupplier::where('product_id', $itemData['product_id'])->count();
                                                if ($suppliersCount > 1) {
                                                    \Filament\Notifications\Notification::make()
                                                        ->title(__('Fournisseur manquant'))
                                                        ->body(__('Dépliez la prestation et sélectionnez un fournisseur avant de générer le Fax.'))
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }
                                            }

                                            if (empty($itemData['id'])) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title(__('Veuillez sauvegarder le dossier avant de générer le fax.'))
                                                    ->danger()
                                                    ->send();
                                                return;
                                            }
                                            
                                            $item = \App\Models\FolderItem::with(['product', 'folder'])->find($itemData['id']);
                                            if (!$item) return;

                                            $item->supplier_id = $itemData['supplier_id'] ?? $item->supplier_id;
                                            $item->product_id = $itemData['product_id'] ?? $item->product_id;
                                            $item->quantity = $itemData['quantity'] ?? $item->quantity;
                                            if (!empty($itemData['service_date'])) {
                                                $item->service_date = Carbon::parse($itemData['service_date']);
                                            }
                                            $item->selected_options = $itemData['selected_options'] ?? $item->selected_options ?? [];
                                            $item->custom_values = $itemData['custom_values'] ?? $item->custom_values ?? [];

                                            $keyFilePath = storage_path('app/google-credentials.json');
                                            if (!file_exists($keyFilePath)) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title(__('Clé Google manquante'))
                                                    ->body(__('Le fichier google-credentials.json est introuvable dans storage/app/.'))
                                                    ->danger()
                                                    ->send();
                                                return;
                                            }

                                            try {
                                                $client = new \Google\Client();
                                                $client->setApplicationName('Portail Takada');
                                                $client->setScopes([\Google\Service\Drive::DRIVE, \Google\Service\Sheets::SPREADSHEETS]);
                                                $client->setAuthConfig($keyFilePath);
                                                $client->setAccessType('offline');

                                                $serviceSheets = new \Google\Service\Sheets($client);
                                                $serviceDrive = new \Google\Service\Drive($client);

                                                $subject = $item->parseSupplierEmailSubject();
                                                $body = $item->parseSupplierEmail();
                                                
                                                $targetSupplier = $item->getTargetSupplier();
                                                $ps = $item->getProductSupplierData();

                                                $faxData = ($ps && $ps->fax_header) ? $ps->fax_header : [];
                                                if (!is_array($faxData)) $faxData = [];
                                                
                                                $writerName = auth()->check() ? auth()->user()->name : 'Takada Travel';
                                                $writerEmail = auth()->check() ? auth()->user()->email : 'resa@kansai-guide.com';

                                                $fComp = $faxData['from_company'] ?? 'TAKADA TRAVEL合同会社';
                                                $fAddr = $faxData['from_address'] ?? "〒532-0012大阪市淀川区木川東3丁目1-23";
                                                $fCont = str_replace('[NOM_AGENT]', $writerName, $faxData['from_contact'] ?? '担当者： [NOM_AGENT]');
                                                $fMail = str_replace('[EMAIL_AGENT]', $writerEmail, $faxData['from_mail'] ?? 'MAIL : [EMAIL_AGENT]');
                                                
                                                $fTelRaw = $faxData['from_tel'] ?? '06-6195-9799';
                                                $fFaxRaw = $faxData['from_fax'] ?? '06-6195-9921';
                                                $fTel = str_starts_with($fTelRaw, '0') ? "'" . $fTelRaw : $fTelRaw;
                                                $fFax = str_starts_with($fFaxRaw, '0') ? "'" . $fFaxRaw : $fFaxRaw;

                                                $currentDate = now()->format('Y/m/d');
                                                
                                                $h1 = $faxData['to_company_name'] ?? ($targetSupplier->name ?? '');
                                                $h2 = $faxData['to_contact_name'] ?? 'ご担当者様';
                                                
                                                $sTelRaw = $faxData['to_tel'] ?? ($targetSupplier->phone ?? '');
                                                $sFaxRaw = $faxData['to_fax'] ?? ($targetSupplier->fax ?? '');
                                                $sTel = str_starts_with($sTelRaw, '0') ? "'" . $sTelRaw : $sTelRaw;
                                                $sFax = str_starts_with($sFaxRaw, '0') ? "'" . $sFaxRaw : $sFaxRaw;

                                                $filename = "FAX_" . \Illuminate\Support\Str::slug($item->product->name ?? 'supplier') . "_" . now()->format('Ymd_His');

                                                $fileMetadata = new \Google\Service\Drive\DriveFile([
                                                    'name' => $filename,
                                                    'mimeType' => 'application/vnd.google-apps.spreadsheet',
                                                    'parents' => [$googleDriveFolderId] 
                                                ]);
                                                
                                                $file = $serviceDrive->files->create($fileMetadata, [
                                                    'fields' => 'id',
                                                    'supportsAllDrives' => true
                                                ]);
                                                
                                                $spreadsheetId = $file->id;

                                                $spreadsheet = $serviceSheets->spreadsheets->get($spreadsheetId);
                                                $sheetId = $spreadsheet->sheets[0]->properties->sheetId;

                                                $values = [
                                                    ['', 'FAX', '', '', '', '', '', '', ''],
                                                    ['', '', '', '', '', '', '', '', ''],
                                                    ['', '', '', '', '', '', $currentDate, '', ''],
                                                    ['', '', '', '', '', '', '', '', ''],
                                                    ['送付先：', '', '', '', '', '', '発信元：', '', ''],
                                                    [$h1, '', '', '', '', '', $fComp, '', ''],
                                                    [$h2, '', '', '', '', '', $fAddr, '', ''],
                                                    ['', '', '', '', '', '', $fCont, '', ''],
                                                    ['', '', '', '', '', '', $fMail, '', ''],
                                                    ['TEL：', $sTel, '', '', '', '', "TEL：{$fTel}", '', ''],
                                                    ['FAX：', $sFax, '', '', '', '', "FAX：{$fFax}", '', ''],
                                                    ['', '', '', '', '', '', '', '', ''],
                                                    ['件名：', $subject, '', '', '', '', '', '', ''],
                                                    [$body, '', '', '', '', '', '', '', ''],
                                                    ['', '', '', '', '', '', '', '', ''],
                                                ];

                                                $bodyObj = new \Google\Service\Sheets\ValueRange(['values' => $values]);
                                                $params = ['valueInputOption' => 'USER_ENTERED'];
                                                $serviceSheets->spreadsheets_values->update($spreadsheetId, 'A1:I17', $bodyObj, $params);

                                                $requests = [
                                                    [
                                                        'updateSheetProperties' => [
                                                            'properties' => ['sheetId' => $sheetId, 'gridProperties' => ['hideGridlines' => true]],
                                                            'fields' => 'gridProperties.hideGridlines'
                                                        ]
                                                    ],
                                                    [
                                                        'repeatCell' => [
                                                            'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 20, 'startColumnIndex' => 0, 'endColumnIndex' => 10],
                                                            'cell' => ['userEnteredFormat' => ['textFormat' => ['fontFamily' => 'Arial', 'fontSize' => 18]]],
                                                            'fields' => 'userEnteredFormat.textFormat'
                                                        ]
                                                    ],
                                                    ['updateDimensionProperties' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'COLUMNS', 'startIndex' => 0, 'endIndex' => 1], 'properties' => ['pixelSize' => 100], 'fields' => 'pixelSize']],
                                                    ['updateDimensionProperties' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'COLUMNS', 'startIndex' => 1, 'endIndex' => 2], 'properties' => ['pixelSize' => 110], 'fields' => 'pixelSize']],
                                                    ['updateDimensionProperties' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'COLUMNS', 'startIndex' => 2, 'endIndex' => 5], 'properties' => ['pixelSize' => 100], 'fields' => 'pixelSize']],
                                                    ['updateDimensionProperties' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'COLUMNS', 'startIndex' => 5, 'endIndex' => 6], 'properties' => ['pixelSize' => 45], 'fields' => 'pixelSize']],
                                                    ['updateDimensionProperties' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'COLUMNS', 'startIndex' => 6, 'endIndex' => 7], 'properties' => ['pixelSize' => 100], 'fields' => 'pixelSize']],
                                                    ['updateDimensionProperties' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'COLUMNS', 'startIndex' => 7, 'endIndex' => 8], 'properties' => ['pixelSize' => 70], 'fields' => 'pixelSize']],
                                                    ['updateDimensionProperties' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'COLUMNS', 'startIndex' => 8, 'endIndex' => 9], 'properties' => ['pixelSize' => 170], 'fields' => 'pixelSize']],
                                                    
                                                    ['updateDimensionProperties' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'ROWS', 'startIndex' => 0, 'endIndex' => 1], 'properties' => ['pixelSize' => 55], 'fields' => 'pixelSize']],
                                                    ['updateDimensionProperties' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'ROWS', 'startIndex' => 6, 'endIndex' => 7], 'properties' => ['pixelSize' => 42], 'fields' => 'pixelSize']],
                                                    
                                                    ['mergeCells' => ['range' => ['sheetId' => $sheetId, 'startRowIndex' => 2, 'endRowIndex' => 3, 'startColumnIndex' => 6, 'endColumnIndex' => 9], 'mergeType' => 'MERGE_ALL']],
                                                    ['mergeCells' => ['range' => ['sheetId' => $sheetId, 'startRowIndex' => 12, 'endRowIndex' => 13, 'startColumnIndex' => 1, 'endColumnIndex' => 9], 'mergeType' => 'MERGE_ALL']],
                                                    ['mergeCells' => ['range' => ['sheetId' => $sheetId, 'startRowIndex' => 13, 'endRowIndex' => 14, 'startColumnIndex' => 0, 'endColumnIndex' => 9], 'mergeType' => 'MERGE_ALL']],
                                                    
                                                    [
                                                        'updateBorders' => [
                                                            'range' => ['sheetId' => $sheetId, 'startRowIndex' => 5, 'endRowIndex' => 11, 'startColumnIndex' => 0, 'endColumnIndex' => 5],
                                                            'innerHorizontal' => ['style' => 'SOLID'],
                                                            'bottom' => ['style' => 'SOLID']
                                                        ]
                                                    ],
                                                    [
                                                        'updateBorders' => [
                                                            'range' => ['sheetId' => $sheetId, 'startRowIndex' => 5, 'endRowIndex' => 11, 'startColumnIndex' => 6, 'endColumnIndex' => 9],
                                                            'innerHorizontal' => ['style' => 'SOLID'],
                                                            'bottom' => ['style' => 'SOLID']
                                                        ]
                                                    ],
                                                    [
                                                        'updateBorders' => [
                                                            'range' => ['sheetId' => $sheetId, 'startRowIndex' => 12, 'endRowIndex' => 13, 'startColumnIndex' => 1, 'endColumnIndex' => 9],
                                                            'bottom' => ['style' => 'SOLID']
                                                        ]
                                                    ],
                                                    [
                                                        'updateBorders' => [
                                                            'range' => ['sheetId' => $sheetId, 'startRowIndex' => 13, 'endRowIndex' => 14, 'startColumnIndex' => 0, 'endColumnIndex' => 9],
                                                            'top' => ['style' => 'SOLID'],
                                                            'bottom' => ['style' => 'SOLID'],
                                                            'left' => ['style' => 'SOLID'],
                                                            'right' => ['style' => 'SOLID'],
                                                        ]
                                                    ],
                                                    ['repeatCell' => [
                                                        'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1, 'startColumnIndex' => 1, 'endColumnIndex' => 2],
                                                        'cell' => ['userEnteredFormat' => ['textFormat' => ['fontFamily' => 'Libre Franklin', 'fontSize' => 24, 'bold' => true]]],
                                                        'fields' => 'userEnteredFormat.textFormat'
                                                    ]],
                                                    ['repeatCell' => [
                                                        'range' => ['sheetId' => $sheetId, 'startRowIndex' => 13, 'endRowIndex' => 14, 'startColumnIndex' => 0, 'endColumnIndex' => 9],
                                                        'cell' => ['userEnteredFormat' => ['wrapStrategy' => 'WRAP', 'verticalAlignment' => 'TOP']],
                                                        'fields' => 'userEnteredFormat(wrapStrategy,verticalAlignment)'
                                                    ]],
                                                    ['repeatCell' => [
                                                        'range' => ['sheetId' => $sheetId, 'startRowIndex' => 2, 'endRowIndex' => 3, 'startColumnIndex' => 6, 'endColumnIndex' => 9],
                                                        'cell' => ['userEnteredFormat' => ['horizontalAlignment' => 'RIGHT']],
                                                        'fields' => 'userEnteredFormat.horizontalAlignment'
                                                    ]],
                                                    ['repeatCell' => [
                                                        'range' => ['sheetId' => $sheetId, 'startRowIndex' => 16, 'endRowIndex' => 17, 'startColumnIndex' => 8, 'endColumnIndex' => 9],
                                                        'cell' => ['userEnteredFormat' => ['horizontalAlignment' => 'RIGHT']],
                                                        'fields' => 'userEnteredFormat.horizontalAlignment'
                                                    ]],
                                                    ['repeatCell' => [
                                                        'range' => ['sheetId' => $sheetId, 'startRowIndex' => 4, 'endRowIndex' => 6, 'startColumnIndex' => 0, 'endColumnIndex' => 9],
                                                        'cell' => ['userEnteredFormat' => ['textFormat' => ['bold' => true]]],
                                                        'fields' => 'userEnteredFormat.textFormat.bold'
                                                    ]],
                                                    ['repeatCell' => [
                                                        'range' => ['sheetId' => $sheetId, 'startRowIndex' => 12, 'endRowIndex' => 13, 'startColumnIndex' => 0, 'endColumnIndex' => 9],
                                                        'cell' => ['userEnteredFormat' => ['textFormat' => ['bold' => true]]],
                                                        'fields' => 'userEnteredFormat.textFormat.bold'
                                                    ]],
                                                ];

                                                $batchUpdateRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest(['requests' => $requests]);
                                                $serviceSheets->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

                                                $permission = new \Google\Service\Drive\Permission([
                                                    'type' => 'anyone',
                                                    'role' => 'writer'
                                                ]);
                                                $serviceDrive->permissions->create($spreadsheetId, $permission, [
                                                    'supportsAllDrives' => true 
                                                ]);

                                                $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit";
                                                $component->getLivewire()->js("window.open('{$url}', '_blank')");

                                            } catch (\Exception $e) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title(__('Erreur de communication avec Google'))
                                                    ->body($e->getMessage())
                                                    ->danger()
                                                    ->send();
                                            }
                                        })
                                ])
                                ->schema([
                                    Group::make()->schema([
                                        Select::make('supplier_id')
                                            ->label(__('Fournisseur de la prestation'))
                                            ->options(function ($get) {
                                                try {
                                                    $productId = $get('product_id'); 
                                                    if (!$productId) return [];
                                                    return \App\Models\ProductSupplier::where('product_id', $productId)
                                                        ->with('supplier')
                                                        ->get()
                                                        ->pluck('supplier.name', 'supplier_id');
                                                } catch (\Exception $e) { return []; }
                                            })
                                            ->afterStateHydrated(function ($component, $state, $get) {
                                                try {
                                                    if (!$state && $get('product_id')) {
                                                        $suppliers = \App\Models\ProductSupplier::where('product_id', $get('product_id'))->pluck('supplier_id')->toArray();
                                                        if (count($suppliers) === 1) {
                                                            $component->state($suppliers[0]);
                                                        }
                                                    }
                                                } catch (\Exception $e) {}
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->hint(function ($get) {
                                                try {
                                                    $productId = $get('product_id');
                                                    if ($productId && !$get('supplier_id')) {
                                                        $count = \App\Models\ProductSupplier::where('product_id', $productId)->count();
                                                        if ($count > 1) {
                                                            return new \Illuminate\Support\HtmlString('<span style="color:red; font-weight:bold;">⚠️ Sélection requise pour générer les documents</span>');
                                                        }
                                                    }
                                                } catch (\Exception $e) {}
                                                return null;
                                            })
                                            ->columnSpan(4),

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
                                                    
                                                    try {
                                                        $suppliers = \App\Models\ProductSupplier::where('product_id', $state)->pluck('supplier_id')->toArray();
                                                        if (count($suppliers) === 1) {
                                                            $set('supplier_id', $suppliers[0]);
                                                        } else {
                                                            $set('supplier_id', null);
                                                        }
                                                    } catch (\Exception $e) {
                                                        $set('supplier_id', null);
                                                    }
                                                }
                                                self::updateItemPrices($set, $get);
                                            })
                                            ->columnSpan(4),

                                        Select::make('item_status_id')
                                            ->relationship('itemStatus', 'name', modifyQueryUsing: function ($query) {
                                                \App\Models\ItemStatus::firstOrCreate(
                                                    ['name' => 'En attente de validation'],
                                                    ['color' => 'warning']
                                                );
                                                return $query;
                                            })
                                            ->label(__('Statut'))
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->default(fn () => \App\Models\ItemStatus::where('name', 'En attente de validation')->value('id'))
                                            ->columnSpan(4),
                                    ])->columns(12),

                                    // 💡 UTILISATION DE requires_invoice
                                    Group::make()->schema([
                                        Placeholder::make('invoice_alert')
                                            ->label('')
                                            ->content(new \Illuminate\Support\HtmlString('<div style="display:flex; align-items:center; gap:0.5rem; background-color: #fef2f2; color: #dc2626; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #f87171; font-weight: bold; font-size: 0.85rem;">⚠️ En attente de facture</div>'))
                                            ->hidden(function ($get) { 
                                                if ($get('invoice_received_at') !== null) {
                                                    return true;
                                                }
                                                $supplierId = $get('supplier_id');
                                                if (!$supplierId) {
                                                    return true;
                                                }
                                                $supplier = \App\Models\Supplier::find($supplierId);
                                                return !($supplier && $supplier->requires_invoice);
                                            })
                                            ->columnSpan(6),

                                        DatePicker::make('invoice_received_at')
                                            ->label(__('Date de réception de la facture'))
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->live() 
                                            ->visible(function ($get) { 
                                                $supplierId = $get('supplier_id');
                                                if (!$supplierId) return false;
                                                $supplier = \App\Models\Supplier::find($supplierId);
                                                return $supplier && $supplier->requires_invoice;
                                            })
                                            ->columnSpan(6),
                                    ])->columns(12),

                                    Group::make()->schema([
                                        DatePicker::make('service_date')
                                            ->label(__('Date'))
                                            ->required()
                                            ->live()
                                            ->minDate(fn ($get) => $get('../../start_date') ? Carbon::parse($get('../../start_date'))->startOfDay() : null)
                                            ->maxDate(fn ($get) => $get('../../end_date') ? Carbon::parse($get('../../end_date'))->endOfDay() : null)
                                            ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get))
                                            ->columnSpan(1),

                                        TextInput::make('quantity')
                                            ->label(__('Total Pax'))
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get))
                                            ->columnSpan(1),

                                        // --- LIGNES D'ACHAT ---
                                        TextInput::make('purchase_unit_price')
                                            ->label(__('Achat Unit. (¥)'))
                                            ->numeric()
                                            ->default(0)
                                            ->live()
                                            ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get))
                                            ->columnSpan(1),

                                        TextInput::make('purchase_total_price')
                                            ->label(__('Total Achat (¥)'))
                                            ->numeric()
                                            ->default(0)
                                            ->readOnly()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        // --- LIGNES DE VENTE ---
                                        TextInput::make('unit_price')
                                            ->label(__('Vente Unit. (¥)'))
                                            ->numeric()
                                            ->default(0) 
                                            ->readOnly() 
                                            ->dehydrated()
                                            ->required(false)
                                            ->columnSpan(1),

                                        TextInput::make('total_price')
                                            ->label(__('Total Vente (¥)'))
                                            ->numeric()
                                            ->default(0) 
                                            ->readOnly() 
                                            ->dehydrated()
                                            ->required(false)
                                            ->columnSpan(1),
                                    ])->columns(6),

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
                                            foreach ($product->custom_field_definitions as $idx => $def) {
                                                $type = $def['type'] ?? 'text';
                                                
                                                $key = !empty($def['key']) ? trim($def['key']) : '';
                                                if (empty($key)) {
                                                    $key = Str::slug($def['name'] ?? 'custom', '_');
                                                }
                                                if (empty($key)) {
                                                    $key = 'custom_field_' . $idx; 
                                                }

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
                                                    $field->rules(['required']);
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
                TextColumn::make('reference')
                    ->label(__('Référence'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('agency.name')
                    ->label(__('Agence'))
                    ->sortable(),

                TextColumn::make('folder_name')
                    ->label(__('Nom du dossier'))
                    ->searchable(),

                TextColumn::make('mainSeller.name')
                    ->label(__('Vendeur Principal'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
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

                TextColumn::make('total_price')
                    ->label(__('Montant total'))
                    ->money('JPY')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                Action::make('download_pdf')
                    ->label(__('Pré-facture'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn ($record) => route('pdf.recapitulatif', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
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