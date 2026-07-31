<?php

namespace App\Filament\Agency\Resources;

use App\Filament\Agency\Resources\AgencyFolderResource\Pages;
use App\Models\Folder;
use App\Models\Hotel;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema; 
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\EditAction;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Blade;
use Filament\Facades\Filament;

class AgencyFolderResource extends Resource
{
    protected static ?string $model = Folder::class;
    
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';
    
    public static function canViewAny(): bool
    {
        return Filament::auth()->check();
    }

    public static function canCreate(): bool
    {
        return Filament::auth()->check();
    }

    public static function canEdit(Model $record): bool
    {
        return Filament::auth()->check() && $record->agency_id === Filament::auth()->user()->agency_id;
    }

    public static function canView(Model $record): bool
    {
        return Filament::auth()->check() && $record->agency_id === Filament::auth()->user()->agency_id;
    }

    public static function canDelete(Model $record): bool
    {
        return Filament::auth()->check() 
            && $record->agency_id === Filament::auth()->user()->agency_id 
            && $record->status === 'draft';
    }

    public static function getNavigationLabel(): string
    {
        return __('Mes Dossiers');
    }

    public static function getModelLabel(): string
    {
        return __('Dossier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Mes Dossiers');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('agency_id', Filament::auth()->user()->agency_id);
    }

    public static function updatePassengerCount($set, $get) {
        \App\Filament\Resources\Folders\FolderResource::updatePassengerCount($set, $get);
    }
    public static function updateItemPrices($set, $get) {
        \App\Filament\Resources\Folders\FolderResource::updateItemPrices($set, $get);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            
            Section::make()
                ->visible(fn (?Model $record) => $record && $record->status === 'draft')
                ->schema([
                    Placeholder::make('draft_notice')
                        ->hiddenLabel()
                        ->content(new HtmlString("
                            <div style='display: flex; align-items: center; justify-content: space-between; background-color: #fef3c7; border: 1px solid #f59e0b; padding: 12px 16px; border-radius: 8px; color: #92400e;'>
                                <div>
                                    <strong style='font-size: 1rem;'>✏️ Dossier en cours de rédaction (Brouillon)</strong>
                                    <p style='margin: 4px 0 0 0; font-size: 0.875rem;'>Vous pouvez modifier ou supprimer vos prestations et informations passagers freely. Une fois votre sélection finalisée, cliquez sur le bouton <b>\"🚀 Valider et transmettre le dossier\"</b> en haut à droite.</p>
                                </div>
                            </div>
                        "))
                ])
                ->columnSpanFull(),

            Group::make()->schema([
                Section::make('Informations Principales')
                    ->columns(2)
                    ->schema([
                        TextInput::make('folder_name')
                            ->label('Nom du dossier / Réf. Groupe')
                            ->required(),

                        TextInput::make('lead_traveler_name')
                            ->label('Nom du pax principal')
                            ->required(),

                        TextInput::make('hotel_booking_name')
                            ->label('Nom réservation hôtel')
                            ->placeholder('Si différent du pax principal')
                            ->columnSpanFull(),

                        Select::make('main_seller_id')
                            ->label('Vendeur principal')
                            ->options(function () {
                                $agencyId = Filament::auth()->user()->agency_id;
                                if (!$agencyId) return [];
                                return \App\Models\User::where('agency_id', $agencyId)->pluck('name', 'id');
                            })
                            ->default(fn () => Filament::auth()->id())
                            ->searchable()
                            ->preload()
                            ->required(),

                        DatePicker::make('start_date')
                            ->label('Date d\'arrivée au Japon')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get)),

                        DatePicker::make('end_date')
                            ->label('Date de départ')
                            ->required()
                            ->live()
                            ->minDate(fn (Get $get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null),

                        Repeater::make('contact_phones')
                            ->label('Contact du pax pendant le séjour')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->defaultItems(1)
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Téléphone')
                                    ->tel()
                                    ->required()
                                    ->placeholder('+33 6...'),
                                TextInput::make('email')
                                    ->label('Adresse E-mail')
                                    ->email()
                                    ->placeholder('pax@email.com'),
                            ])
                            ->columnSpanFull()
                            ->columns(2),
                    ]),

                Section::make('Informations du Premier Hôtel')
                    ->columns(2)
                    ->schema([
                        Select::make('hotel_preset')
                            ->label('🏢 Rechercher dans la base d\'hôtels')
                            ->placeholder('Tapez le nom d\'un hôtel pour pré-remplir...')
                            ->options(fn () => Hotel::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state && $hotel = Hotel::find($state)) {
                                    $set('first_hotel_name', $hotel->name);

                                    $fullAddress = $hotel->address ?? '';
                                    if (!empty($hotel->phone)) {
                                        $fullAddress .= ($fullAddress ? "\nTél : " : "Tél : ") . $hotel->phone;
                                    }

                                    $set('first_hotel_address', $fullAddress);
                                    $set('first_hotel_google_maps_url', $hotel->google_maps_url);
                                }
                            })
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        TextInput::make('first_hotel_name')
                            ->label('1er Hôtel (Nom)')
                            ->placeholder('Ex: Hotel Gracery Shinjuku'),

                        DatePicker::make('first_hotel_check_in')
                            ->label('Date Check-in 1er Hôtel')
                            ->live()
                            ->minDate(fn (Get $get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null)
                            ->maxDate(fn (Get $get) => $get('end_date') ? Carbon::parse($get('end_date'))->endOfDay() : null)
                            ->afterOrEqual('start_date')
                            ->beforeOrEqual('end_date')
                            ->validationMessages([
                                'after_or_equal' => 'Doit être après ou le jour de l\'arrivée.',
                                'before_or_equal' => 'Doit être avant ou le jour du départ.',
                            ]),

                        Textarea::make('first_hotel_address')
                            ->label('Adresse du premier hôtel')
                            ->placeholder('Adresse complète pour l\'envoi éventuel de documents...')
                            ->rows(2)
                            ->columnSpanFull(),

                        TextInput::make('first_hotel_google_maps_url')
                            ->label('Lien Google Maps')
                            ->url()
                            ->placeholder('https://maps.google.com/...')
                            ->live()
                            ->columnSpanFull(),

                        Placeholder::make('first_hotel_google_maps_link')
                            ->hiddenLabel()
                            ->visible(fn (Get $get) => !empty($get('first_hotel_google_maps_url')))
                            ->content(function (Get $get) {
                                $url = $get('first_hotel_google_maps_url');
                                if (!$url) return '';
                                return new HtmlString("
                                    <div style='margin-top: 4px;'>
                                        <a href='{$url}' target='_blank' style='display: inline-flex; align-items: center; gap: 8px; background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 8px 14px; border-radius: 6px; font-weight: 600; font-size: 0.875rem; text-decoration: none;'>
                                            📍 Ouvrir la localisation sur Google Maps ↗
                                        </a>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Liste des Pax')
                    ->schema([
                        Repeater::make('folderPassengers')
                            ->relationship()
                            ->hiddenLabel()
                            ->addActionLabel('Ajouter un passager')
                            ->addable(fn (?Model $record, Get $get) => $record === null || $record->status === 'draft' || $get('status') === 'draft')
                            ->deletable(fn (?Model $record, Get $get) => $record === null || $record->status === 'draft' || $get('status') === 'draft')
                            ->reorderable(false)
                            ->itemLabel(function (array $state) {
                                $name = trim(($state['first_name'] ?? '') . ' ' . ($state['last_name'] ?? ''));
                                if (!empty($name)) {
                                    return new HtmlString("<span style='color:#096a61; font-weight:600;'>👤 {$name}</span>");
                                }
                                return new HtmlString("<span style='color:#4b5563; font-weight:600;'>👤 Passager (à renseigner)</span>");
                            })
                            ->schema([
                                Placeholder::make('condensed_passenger')
                                    ->hiddenLabel()
                                    ->visible(fn (?Model $record, Get $get) => $record !== null && $record->status !== 'draft' && $get('../../status') !== 'draft')
                                    ->content(function (Get $get) {
                                        $paxId = $get('id');
                                        $pax = $paxId ? \App\Models\FolderPassenger::find($paxId) : null;
                                        $firstName = $get('first_name') ?? $pax?->first_name ?? '';
                                        $lastName = $get('last_name') ?? $pax?->last_name ?? '';
                                        $birthDate = $get('birth_date') ?? $pax?->birth_date;
                                        $nat = $get('nationality') ?? $pax?->nationality ?? '';
                                        $diet = $get('dietary_restrictions') ?? $pax?->dietary_restrictions;
                                        $mobility = $get('mobility_concerns') ?? $pax?->mobility_concerns;

                                        $name = trim($firstName . ' ' . $lastName);
                                        $age = $birthDate ? \Carbon\Carbon::parse($birthDate)->age . ' ans' : '';

                                        $warns = [];
                                        if (!empty($diet)) $warns[] = "🚫 Allergies: {$diet}";
                                        if (!empty($mobility)) $warns[] = "♿ PMR: {$mobility}";
                                        $warnHtml = '';
                                        if (count($warns) > 0) {
                                            $warnHtml = "<div style='margin-top:0.5rem; font-size:0.8rem; color:#dc2626; font-weight:600;'>" . implode('<br>', $warns) . "</div>";
                                        }

                                        return new HtmlString("
                                            <div style='padding:0.75rem 1rem; border-left:4px solid #096a61; background:#f9fafb; border-radius:0 0.5rem 0.5rem 0;'>
                                                <div style='font-weight:bold; color:#111827; font-size:1.05rem;'>👤 {$name} <span style='font-weight:normal; color:#6b7280; font-size:0.9rem;'>({$age}) - {$nat}</span></div>
                                                {$warnHtml}
                                            </div>
                                        ");
                                    }),

                                Group::make()->schema([
                                    Group::make()->schema([
                                        TextInput::make('last_name')->label('Nom')->required(),
                                        TextInput::make('first_name')->label('Prénom')->required(),
                                        DatePicker::make('birth_date')
                                            ->label('Date de naissance')
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get)),
                                        TextInput::make('nationality')->label('Nationalité')->required(),
                                    ])->columns(4),
                                    Textarea::make('dietary_restrictions')->label('Allergies')->rows(1),
                                    Textarea::make('mobility_concerns')->label('Besoins PMR')->rows(1),
                                ])->visible(fn (?Model $record, Get $get) => $record === null || $record->status === 'draft' || $get('../../status') === 'draft'),
                            ])
                            ->deleteAction(fn ($action) => $action
                                ->label('Annuler')
                                ->icon('heroicon-m-x-mark')
                                ->color('danger')
                                ->visible(fn (?Model $record, Get $get) => $record === null || $record->status === 'draft' || $get('../../status') === 'draft')
                            )
                    ]),

                Section::make('Prestations demandées')
                    ->description('Vos prestations réservées et le suivi de leur statut.')
                    ->visible(fn (?Model $record) => $record !== null)
                    ->headerActions([
                        Action::make('addPrestationModal')
                            ->label('Ajouter une prestation')
                            ->icon('heroicon-m-plus')
                            ->color('primary')
                            ->modalHeading('Ajouter une nouvelle prestation')
                            ->modalWidth('4xl')
                            ->modalSubmitActionLabel('Ajouter cette prestation au dossier')
                            ->visible(fn (?Model $record) => $record !== null)
                            ->form([
                                Select::make('product_id')
                                    ->label('Produit / Activité')
                                    ->options(function () {
                                        return \App\Models\Product::where('is_public', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $options = \App\Models\ProductOption::where('product_id', $state)->get();
                                            foreach ($options as $opt) {
                                                $set("opt_enabled_{$opt->id}", false);
                                                $set("opt_qty_{$opt->id}", 1);
                                            }
                                        }
                                    }),

                                Group::make()->schema([
                                    DatePicker::make('service_date')
                                        ->label('Date souhaitée')
                                        ->required()
                                        ->live()
                                        ->minDate(fn (?Model $record) => $record?->start_date ? Carbon::parse($record->start_date)->startOfDay() : null)
                                        ->maxDate(fn (?Model $record) => $record?->end_date ? Carbon::parse($record->end_date)->endOfDay() : null),

                                    TextInput::make('quantity')
                                        ->label('Participants (Nombre de Pax)')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->maxValue(fn (Get $get) => \App\Models\Product::find($get('product_id'))?->max_pax)
                                        ->extraInputAttributes(function (Get $get) {
                                            $max = \App\Models\Product::find($get('product_id'))?->max_pax;
                                            if ($max) {
                                                return [
                                                    'max' => $max,
                                                    'oninput' => "if(parseInt(this.value) > $max) this.value = $max;"
                                                ];
                                            }
                                            return [];
                                        })
                                        ->live()
                                        ->required(),
                                ])->columns(2)->visible(fn (Get $get) => !empty($get('product_id'))),

                                Group::make()
                                    ->visible(fn (Get $get) => !empty($get('product_id')) && \App\Models\ProductOption::where('product_id', $get('product_id'))->exists())
                                    ->schema(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) return [];

                                        $options = \App\Models\ProductOption::where('product_id', $productId)->get();
                                        if ($options->isEmpty()) return [];

                                        $schema = [];
                                        foreach ($options as $opt) {
                                            $optId = $opt->id;
                                            $formattedPrice = number_format($opt->price_modifier ?? 0, 0, '.', ' ') . ' ¥';
                                            
                                            $billingBadge = match ($opt->billing_type) {
                                                'per_pax' => "{$formattedPrice} / pax",
                                                'per_booking' => "{$formattedPrice} (Prix fixe)",
                                                'manual' => "{$formattedPrice} / unité",
                                                default => $formattedPrice,
                                            };

                                            $toggle = Toggle::make("opt_enabled_{$optId}")
                                                ->label("{$opt->name} (+ {$billingBadge})")
                                                ->inline(false)
                                                ->live();

                                            if ($opt->billing_type === 'manual') {
                                                $qtyInput = TextInput::make("opt_qty_{$optId}")
                                                    ->label('Quantité au choix')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->minValue(1)
                                                    ->required(fn (Get $get) => (bool) $get("opt_enabled_{$optId}"))
                                                    ->visible(fn (Get $get) => (bool) $get("opt_enabled_{$optId}"))
                                                    ->live();

                                                $schema[] = Group::make()
                                                    ->schema([$toggle, $qtyInput])
                                                    ->columns(2);
                                            } else {
                                                $schema[] = $toggle;
                                            }
                                        }

                                        return [
                                            Section::make('Options tarifaires complémentaires')
                                                ->description('Cochez les options souhaitées selon leur mode de facturation.')
                                                ->schema($schema)
                                        ];
                                    }),

                                Group::make()
                                    ->visible(fn (Get $get) => !empty($get('product_id')))
                                    ->schema(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) return [];

                                        $product = \App\Models\Product::find($productId);
                                        if (!$product || empty($product->custom_field_definitions)) return [];

                                        $paxCount = max(1, (int) ($get('quantity') ?? 1));

                                        $fields = [];
                                        foreach ($product->custom_field_definitions as $def) {
                                            $type = $def['type'] ?? 'text';
                                            $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                                            $baseLabel = $def['name'] ?? 'Information';
                                            $isPerPax = $def['is_per_passenger'] ?? false;
                                            $isRequired = ($def['is_required'] ?? false) && $type !== 'toggle';

                                            if ($isPerPax) {
                                                for ($i = 1; $i <= $paxCount; $i++) {
                                                    $formKey = "custom_{$key}_pax_{$i}";
                                                    $label = "{$baseLabel} (Pax {$i})";

                                                    $field = match ($type) {
                                                        'textarea' => Textarea::make($formKey)->label($label)->rows(2),
                                                        'number' => TextInput::make($formKey)->numeric()->label($label),
                                                        'date' => DatePicker::make($formKey)->label($label),
                                                        'toggle' => Toggle::make($formKey)->label($label)->inline(false),
                                                        'select' => Select::make($formKey)->label($label)->options(array_combine($def['choices'] ?? [], $def['choices'] ?? [])),
                                                        'file' => \Filament\Forms\Components\FileUpload::make($formKey)
                                                            ->label($label)
                                                            ->disk('public')
                                                            ->directory('folders/custom_fields')
                                                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                                            ->maxSize(10240)
                                                            ->preserveFilenames()
                                                            ->downloadable()
                                                            ->openable(),
                                                        default => TextInput::make($formKey)->label($label),
                                                    };
                                                    if ($isRequired) $field->required();
                                                    $fields[] = $field;
                                                }
                                            } else {
                                                $formKey = "custom_{$key}";
                                                $label = $baseLabel;

                                                $field = match ($type) {
                                                    'textarea' => Textarea::make($formKey)->label($label)->rows(2),
                                                    'number' => TextInput::make($formKey)->numeric()->label($label),
                                                    'date' => DatePicker::make($formKey)->label($label),
                                                    'toggle' => Toggle::make($formKey)->label($label)->inline(false),
                                                    'select' => Select::make($formKey)->label($label)->options(array_combine($def['choices'] ?? [], $def['choices'] ?? [])),
                                                    'file' => \Filament\Forms\Components\FileUpload::make($formKey)
                                                        ->label($label)
                                                        ->disk('public')
                                                        ->directory('folders/custom_fields')
                                                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                                        ->maxSize(10240)
                                                        ->preserveFilenames()
                                                        ->downloadable()
                                                        ->openable(),
                                                    default => TextInput::make($formKey)->label($label),
                                                };
                                                if ($isRequired) $field->required();
                                                $fields[] = $field;
                                            }
                                        }
                                        
                                        if (empty($fields)) return [];

                                        return [
                                            Section::make('Informations requises par le fournisseur')
                                                ->schema($fields)->columns(2)
                                        ];
                                    }),

                                Group::make()
                                    ->visible(fn (Get $get) => !empty($get('product_id')) && !empty($get('service_date')))
                                    ->schema([
                                        Placeholder::make('estimated_price_display')
                                            ->hiddenLabel()
                                            ->content(function (Get $get) {
                                                $productId = $get('product_id');
                                                $date = $get('service_date');
                                                $qty = (int) ($get('quantity') ?? 1);
                                                
                                                if (!$productId || !$date) return new HtmlString('<span class="text-gray-500">Sélectionnez une date pour estimer le prix.</span>');
                                                
                                                $product = \App\Models\Product::with(['productPeriods.productPrices', 'productOptions'])->find($productId);
                                                if (!$product) return 'Produit introuvable.';
                                                
                                                $mdStr = Carbon::parse($date)->format('m-d');
                                                $matchedPrice = null;
                                                if ($product->productPeriods) {
                                                    foreach ($product->productPeriods as $period) {
                                                        if (!$period->start_date || !$period->end_date) continue;
                                                        
                                                        $inPeriod = false;
                                                        if ($period->start_date <= $period->end_date) {
                                                            $inPeriod = ($mdStr >= $period->start_date && $mdStr <= $period->end_date);
                                                        } else {
                                                            $inPeriod = ($mdStr >= $period->start_date || $mdStr <= $period->end_date);
                                                        }
                                                        
                                                        if ($inPeriod && $period->productPrices) {
                                                            $validPrices = $period->productPrices->where('min_pax', '<=', $qty)->where('max_pax', '>=', $qty);
                                                            if ($validPrices->isNotEmpty()) {
                                                                $matchedPrice = $validPrices->first()->price;
                                                            } else {
                                                                $matchedPrice = $period->productPrices->sortByDesc('max_pax')->first()->price ?? 0;
                                                            }
                                                            break;
                                                        }
                                                    }
                                                }
                                                
                                                $baseUnitPrice = $matchedPrice ?? 0;
                                                $baseTotal = $baseUnitPrice * $qty;
                                                
                                                $optionsTotal = 0;
                                                $optRows = [];
                                                if ($product->productOptions) {
                                                    foreach ($product->productOptions as $opt) {
                                                        if ($get("opt_enabled_{$opt->id}")) {
                                                            $mod = $opt->price_modifier ?? 0;
                                                            if ($opt->billing_type === 'per_pax') {
                                                                $optTotal = $mod * $qty;
                                                                $calcText = number_format($mod, 0, '.', ' ') . " ¥ × {$qty} pax";
                                                            } elseif ($opt->billing_type === 'per_booking') {
                                                                $optTotal = $mod;
                                                                $calcText = "forfait fixe";
                                                            } elseif ($opt->billing_type === 'manual') {
                                                                $optQty = (int) ($get("opt_qty_{$opt->id}") ?? 1);
                                                                $optTotal = $mod * $optQty;
                                                                $calcText = number_format($mod, 0, '.', ' ') . " ¥ × {$optQty}";
                                                            }
                                                            $optionsTotal += $optTotal;
                                                            $optRows[] = "
                                                                <div style='display:flex; justify-content:space-between; color:#475569; padding-left:12px; margin-top:2px; font-size:0.825rem;'>
                                                                    <span>└ <i>Option : " . e($opt->name) . "</i> ({$calcText})</span>
                                                                    <span style='color:#0284c7; font-weight:600;'>+ " . number_format($optTotal, 0, '.', ' ') . " ¥</span>
                                                                </div>
                                                            ";
                                                        }
                                                    }
                                                }
                                                
                                                $grandTotal = $baseTotal + $optionsTotal;
                                                
                                                return new HtmlString("
                                                    <div style='padding: 1rem; border-radius: 0.5rem; background-color: #f0fdf4; border: 1px solid #bbf7d0; margin-top: 1rem;'>
                                                        <div style='font-weight:600; color:#166534; font-size:0.9rem; margin-bottom:0.35rem;'>Calcul estimatif du prix :</div>
                                                        <div style='display:flex; justify-content:space-between; font-size:0.875rem; color:#15803d;'>
                                                            <span>• Tarif de base (" . number_format($baseUnitPrice, 0, '.', ' ') . " ¥ × {$qty} pax)</span>
                                                            <span><b>" . number_format($baseTotal, 0, '.', ' ') . " ¥</b></span>
                                                        </div>
                                                        " . implode('', $optRows) . "
                                                        <div style='border-top:1px solid #86efac; margin-top:0.5rem; padding-top:0.35rem; display:flex; justify-content:space-between; align-items:center;'>
                                                            <span style='font-weight:bold; color:#166534;'>Prix total estimé :</span>
                                                            <span style='font-size:1.35rem; font-weight:bold; color:#15803d;'>" . number_format($grandTotal, 0, '.', ' ') . " ¥</span>
                                                        </div>
                                                    </div>
                                                ");
                                            })
                                    ]),
                            ])
                            ->action(function (array $data, ?Model $record) {
                                if (!$record) return;

                                $status = \App\Models\ItemStatus::firstOrCreate(
                                    ['name' => 'En attente de validation'],
                                    ['color' => 'warning']
                                );

                                $formattedOptions = [];
                                $options = \App\Models\ProductOption::where('product_id', $data['product_id'])->get();
                                foreach ($options as $opt) {
                                    if (!empty($data["opt_enabled_{$opt->id}"])) {
                                        $formattedOptions[] = [
                                            'product_option_id' => $opt->id,
                                            'quantity' => (int) ($data["opt_qty_{$opt->id}"] ?? 1),
                                        ];
                                    }
                                }

                                $customValues = [];
                                $product = \App\Models\Product::find($data['product_id']);
                                if ($product && !empty($product->custom_field_definitions)) {
                                    foreach ($product->custom_field_definitions as $def) {
                                        $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                                        $isPerPax = $def['is_per_passenger'] ?? false;
                                        $paxCount = max(1, (int) ($data['quantity'] ?? 1));

                                        if ($isPerPax) {
                                            $paxVals = [];
                                            for ($i = 1; $i <= $paxCount; $i++) {
                                                $fk = "custom_{$key}_pax_{$i}";
                                                if (isset($data[$fk]) && $data[$fk] !== '') {
                                                    $v = $data[$fk];
                                                    if (is_bool($v)) $v = $v ? 'Oui' : 'Non';
                                                    $paxVals[] = "Pax {$i}: {$v}";
                                                }
                                            }
                                            if (!empty($paxVals)) {
                                                $customValues[$key] = implode("\n", $paxVals);
                                            }
                                        } else {
                                            $fk = "custom_{$key}";
                                            if (isset($data[$fk])) {
                                                $customValues[$key] = $data[$fk];
                                            }
                                        }
                                    }
                                }

                                $folderItem = \App\Models\FolderItem::create([
                                    'folder_id' => $record->id,
                                    'product_id' => $data['product_id'],
                                    'service_date' => $data['service_date'],
                                    'quantity' => $data['quantity'] ?? 1,
                                    'selected_options' => $formattedOptions,
                                    'custom_values' => $customValues,
                                    'item_status_id' => $status->id,
                                    'unit_price' => 0,
                                    'total_price' => 0,
                                ]);

                                \App\Filament\Resources\Folders\FolderResource::updateItemPrices(
                                    function($k, $v) use ($folderItem) { $folderItem->update([$k => $v]); },
                                    function($k) use ($folderItem) { return $folderItem->{$k}; }
                                );

                                $record->refresh();
                                $totalSale = $record->folderItems->sum('total_price') + ($record->folder_fee ?? 0);
                                $record->update(['total_price' => $totalSale]);

                                \Filament\Notifications\Notification::make()
                                    ->title('Prestation ajoutée au dossier !')
                                    ->body('La prestation a été enregistrée.')
                                    ->success()
                                    ->send();

                                return redirect(request()->header('Referer'));
                            })
                    ])
                    ->schema([
                        Repeater::make('folderItems')
                            ->relationship()
                            ->saveRelationshipsUsing(null)
                            ->dehydrated(false)
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->extraItemActions([
                                Action::make('deleteItem')
                                    ->label('Supprimer')
                                    ->icon('heroicon-m-trash')
                                    ->color('danger')
                                    ->requiresConfirmation()
                                    ->modalHeading('Supprimer cette prestation ?')
                                    ->modalSubheading('Cette action retirera la prestation de votre dossier.')
                                    ->visible(fn ($record, Get $get) => ($record?->status === 'draft') || ($get('../../status') === 'draft'))
                                    ->action(function (array $arguments, Repeater $component) {
                                        $state = $component->getState();
                                        $itemData = $state[$arguments['item']] ?? [];
                                        if (!empty($itemData['id'])) {
                                            $item = \App\Models\FolderItem::find($itemData['id']);
                                            if ($item) {
                                                $folder = $item->folder;
                                                $item->delete();
                                                if ($folder) {
                                                    $record = $folder->fresh();
                                                    $totalSale = $record->folderItems->sum('total_price') + ($record->folder_fee ?? 0);
                                                    $record->update(['total_price' => $totalSale]);
                                                }
                                            }
                                        }
                                    }),
                            ])
                            ->itemLabel(function (array $state) {
                                return new HtmlString("<span style='color:#096a61; font-weight:600;'>Prestation enregistrée</span>");
                            })
                            ->schema([
                                Placeholder::make('condensed_item')
                                    ->hiddenLabel()
                                    ->content(function (Get $get) {
                                        $item = \App\Models\FolderItem::with(['product', 'itemStatus'])->find($get('id'));
                                        if (!$item) return '';

                                        $productName = $item->product->name ?? 'Prestation';
                                        $date = $item->service_date ? \Carbon\Carbon::parse($item->service_date)->format('d/m/Y') : 'À définir';
                                        $qty = $item->quantity ?? 1;

                                        $status = $item->itemStatus;
                                        $statusName = $status->name ?? 'En attente de validation';
                                        $colorName = $status->color ?? 'warning';
                                        $statusColor = match($colorName) {
                                            'success' => '#16a34a',
                                            'warning' => '#d97706',
                                            'danger' => '#dc2626',
                                            'info' => '#2563eb',
                                            default => '#d97706',
                                        };
                                        $statusBadge = "<span style='background:{$statusColor}15; color:{$statusColor}; padding:4px 12px; border-radius:99px; font-size:0.75rem; font-weight:bold; letter-spacing:0.05em; border:1px solid {$statusColor}30;'>📌 {$statusName}</span>";

                                        // Décomposition tarifaire
                                        $optionsTotal = 0;
                                        $optRows = [];
                                        $selectedOptions = $item->selected_options ?? [];

                                        if (is_array($selectedOptions) && count($selectedOptions) > 0) {
                                            foreach ($selectedOptions as $optData) {
                                                if (!empty($optData['product_option_id'])) {
                                                    $optModel = \App\Models\ProductOption::find($optData['product_option_id']);
                                                    if ($optModel) {
                                                        $mod = (float) ($optModel->price_modifier ?? 0);
                                                        $optQty = (int) ($optData['quantity'] ?? 1);

                                                        if ($optModel->billing_type === 'per_pax') {
                                                            $optTotal = $mod * $qty;
                                                            $calcText = number_format($mod, 0, '.', ' ') . " ¥ × {$qty} pax";
                                                        } elseif ($optModel->billing_type === 'manual') {
                                                            $optTotal = $mod * $optQty;
                                                            $calcText = number_format($mod, 0, '.', ' ') . " ¥ × {$optQty}";
                                                        } else {
                                                            $optTotal = $mod;
                                                            $calcText = "forfait fixe";
                                                        }
                                                        $optionsTotal += $optTotal;

                                                        $optRows[] = "
                                                            <div style='display:flex; justify-content:space-between; color:#475569; padding-left:12px; margin-top:2px; font-size:0.825rem;'>
                                                                <span>└ <i>Option : " . e($optModel->name) . "</i> <span style='color:#6b7280;'>({$calcText})</span></span>
                                                                <span style='color:#0284c7; font-weight:600;'>+ " . number_format($optTotal, 0, '.', ' ') . " ¥</span>
                                                            </div>
                                                        ";
                                                    }
                                                }
                                            }
                                        }

                                        $itemTotal = (float) ($item->total_price ?? 0);
                                        $baseTotal = max(0, $itemTotal - $optionsTotal);
                                        $baseUnitPrice = $qty > 0 ? ($baseTotal / $qty) : 0;

                                        $priceBreakdownHtml = "
                                            <div style='margin-top:0.85rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:0.5rem; padding:0.75rem;'>
                                                <div style='font-weight:600; color:#1e3a8a; font-size:0.85rem; margin-bottom:0.35rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.25rem;'>
                                                    📊 Décomposition du tarif :
                                                </div>
                                                <div style='display:flex; justify-content:space-between; font-size:0.875rem; color:#374151;'>
                                                    <span>• <b>Tarif de base :</b> " . number_format($baseUnitPrice, 0, '.', ' ') . " ¥ × {$qty} pax</span>
                                                    <span><b>" . number_format($baseTotal, 0, '.', ' ') . " ¥</b></span>
                                                </div>
                                                " . implode('', $optRows) . "
                                                <div style='display:flex; justify-content:space-between; border-top:1px solid #cbd5e1; margin-top:0.5rem; padding-top:0.35rem; font-weight:bold; color:#096a61; font-size:0.95rem;'>
                                                    <span>Total de la prestation :</span>
                                                    <span>" . number_format($itemTotal, 0, '.', ' ') . " ¥</span>
                                                </div>
                                            </div>
                                        ";

                                        $customValuesHtml = '';
                                        $customValues = $item->custom_values ?? [];
                                        $product = $item->product;
                                        if ($product && !empty($product->custom_field_definitions) && is_array($customValues) && count($customValues) > 0) {
                                            $cvs = [];
                                            foreach ($product->custom_field_definitions as $def) {
                                                $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                                                $label = $def['name'] ?? 'Information';
                                                
                                                if (isset($customValues[$key]) && $customValues[$key] !== '') {
                                                    $val = $customValues[$key];
                                                    
                                                    if (is_bool($val)) {
                                                        $val = $val ? 'Oui' : 'Non';
                                                    } elseif (is_array($val)) {
                                                        $val = implode(', ', \Illuminate\Support\Arr::flatten($val));
                                                    }
                                                    
                                                    if (($def['type'] ?? '') === 'file') {
                                                        $val = preg_replace_callback('/(folders\/custom_fields\/.*?\.(?:pdf|jpg|jpeg|png|doc|docx))/i', function ($m) {
                                                            $path = trim($m[1]);
                                                            $url = route('file.download', ['path' => $path]);
                                                            return "<div style='margin-top: 6px; margin-bottom: 6px;'><a href=\"{$url}\" style=\"display: inline-block; background-color: #ecfdf5; color: #059669; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 0.85rem; border: 1px solid #a7f3d0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);\">📄 Télécharger / Voir le document</a></div>";
                                                        }, (string)$val);
                                                    }
                                                    
                                                    $cvs[] = "<b>{$label} :</b> {$val}";
                                                }
                                            }
                                            if (count($cvs) > 0) {
                                                $customValuesHtml = "<div style='margin-top:0.75rem; font-size:0.85rem; color:#4b5563; padding-top:0.75rem; border-top:1px dashed #e5e7eb;'>" . implode('<br>', $cvs) . "</div>";
                                            }
                                        }

                                        return new HtmlString("
                                            <div style='padding:1.25rem; border-radius:0.75rem; border:1px solid #e5e7eb; background:#ffffff; box-shadow:0 1px 3px rgba(0,0,0,0.05);'>
                                                <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;'>
                                                    <strong style='font-size:1.15rem; color:#111827;'>{$productName}</strong>
                                                    {$statusBadge}
                                                </div>
                                                <div style='display:flex; gap:2rem; font-size:0.95rem; color:#374151; background:#f3f4f6; padding:0.75rem 1.25rem; border-radius:0.5rem;'>
                                                    <span>📅 Date : <b>{$date}</b></span>
                                                    <span>👥 Pax : <b>{$qty}</b></span>
                                                </div>
                                                {$priceBreakdownHtml}
                                                {$customValuesHtml}
                                            </div>
                                        ");
                                    }),
                            ]),
                    ]),
            ])->columnSpan(['lg' => 2]),

            Group::make()->schema([
                Section::make('Récapitulatif & Statut')
                    ->schema([
                        Placeholder::make('status_display')
                            ->label('Statut du dossier')
                            ->content(function ($record) {
                                if (!$record || !$record->status) return 'BROUILLON (NOUVEAU)';
                                return match ($record->status) {
                                    'draft' => 'BROUILLON',
                                    'pending' => 'EN ATTENTE DE VALIDATION',
                                    'confirmed' => 'CONFIRMÉ / TRANSMIS',
                                    'completed' => 'VOYAGE TERMINÉ',
                                    'cancelled' => 'ANNULÉ',
                                    default => strtoupper($record->status),
                                };
                            })
                            ->extraAttributes(['class' => 'text-primary-600 font-bold']),

                        Placeholder::make('total_price_display')
                            ->label('Montant total estimé (¥)')
                            ->content(function (Get $get) {
                                $items = $get('folderItems') ?? [];
                                $total = array_sum(array_map(fn($i) => (float)($i['total_price'] ?? 0), $items));
                                return number_format($total, 0, '.', ' ') . ' ¥';
                            })
                            ->extraAttributes(['class' => 'text-xl font-bold']),
                            
                        Hidden::make('status')->default('draft'),
                        Hidden::make('total_price')->default(0),
                        Hidden::make('folder_fee')->default(0),
                        Hidden::make('agency_id')->default(fn () => Filament::auth()->user()->agency_id),
                    ]),

                Section::make('Informations de Vol')
                    ->schema([
                        Textarea::make('flight_info')
                            ->label('Vols (Arrivée/Départ)')
                            ->placeholder('Ex: Vol AF276 Arrivée Haneda 10:30...')
                            ->rows(3),
                    ]),

                Section::make('📂 Documents partagés')
                    ->description('Fichiers mis à disposition par l\'équipe Takada.')
                    ->schema([
                        Placeholder::make('documents_display')
                            ->hiddenLabel()
                            ->content(function (?Model $record) {
                                if (!$record || empty($record->documents) || !is_array($record->documents)) {
                                    return new HtmlString('<span style="color: #6b7280; font-style: italic; font-size: 0.875rem;">Aucun document partagé pour le moment.</span>');
                                }

                                $html = '<div style="display: flex; flex-direction: column; gap: 0.75rem;">';
                                foreach ($record->documents as $document) {
                                    $url = \Illuminate\Support\Facades\Storage::url($document);
                                    $name = basename($document);
                                    
                                    $html .= "
                                    <div style=\"display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.375rem;\">
                                        <div style=\"display: flex; align-items: center; gap: 0.75rem; overflow: hidden;\">
                                            <svg style=\"width: 1.25rem; height: 1.25rem; color: #9ca3af; flex-shrink: 0;\" xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"1.5\" stroke=\"currentColor\">
                                                <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z\" />
                                            </svg>
                                            <a href=\"{$url}\" target=\"_blank\" style=\"font-size: 0.875rem; font-weight: 500; color: #059669; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;\">
                                                {$name}
                                            </a>
                                        </div>
                                        <a href=\"{$url}\" download style=\"color: #6b7280; padding: 0.25rem; flex-shrink: 0;\">
                                            <svg style=\"width: 1.25rem; height: 1.25rem;\" xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"1.5\" stroke=\"currentColor\">
                                                <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3\" />
                                            </svg>
                                        </a>
                                    </div>";
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),

            ])->columnSpan(['lg' => 1]),

            Section::make('Communication avec l\'équipe Takada')
                ->description('Échangez directement avec nous ici pour toute question ou envoi de fichier sur ce dossier.')
                ->schema([
                    Placeholder::make('chat_placeholder')
                        ->hiddenLabel()
                        ->content(fn (?Model $record) => $record ? new HtmlString(
                            Blade::render('@livewire("folder-chat", ["folder" => $folder])', ['folder' => $record])
                        ) : '')
                ])
                ->hidden(fn (?Model $record) => $record === null)
                ->columnSpanFull(),

        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('folder_name')
                    ->label('Dossier / Réf.')
                    ->description(fn (Folder $record): string => "Réf: {$record->reference}")
                    ->searchable(['folder_name', 'reference'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('lead_traveler_name')
                    ->label('Pax Leader')
                    ->searchable(),

                Tables\Columns\TextColumn::make('mainSeller.name')
                    ->label('Vendeur')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Arrivée')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Brouillon',
                        'pending' => 'En attente',
                        'confirmed' => 'Confirmé',
                        'completed' => 'Terminé',
                        'cancelled' => 'Annulé',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total Estimé')
                    ->money('JPY')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->label('Modifier')
                    ->icon('heroicon-o-pencil-square'),

                Action::make('cancelDraftTable')
                    ->label('Annuler')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Annuler et supprimer ce dossier brouillon ?')
                    ->modalDescription('Ce dossier n\'a pas encore été transmis. Sa suppression entraînera le retrait définitif du brouillon.')
                    ->modalSubmitActionLabel('Oui, supprimer le brouillon')
                    ->modalCancelActionLabel('Conserver le brouillon')
                    ->visible(fn ($record) => $record && $record->status === 'draft')
                    ->action(function ($record) {
                        $record->delete();

                        \Filament\Notifications\Notification::make()
                            ->title('Brouillon supprimé')
                            ->body('Le dossier brouillon a été annulé avec succès.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgencyFolders::route('/'),
            'create' => Pages\CreateAgencyFolder::route('/create'),
            'edit' => Pages\EditAgencyFolder::route('/{record}/edit'),
        ];
    }
}