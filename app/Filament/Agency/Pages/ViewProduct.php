<?php

namespace App\Filament\Agency\Pages;

use App\Models\Product;
use App\Models\Folder;
use App\Models\FolderItem;
use App\Models\FolderPassenger;
use App\Models\TrainStation;
use App\Models\BusStation;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Panel;
use BackedEnum;
use UnitEnum;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Filament\Facades\Filament;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Attributes\Computed;
use Illuminate\Support\HtmlString;
use Livewire\WithFileUploads;

class ViewProduct extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = null; 

    protected string $view = 'filament.agency.pages.view-product';
    protected static ?string $title = null;

    public function getTitle(): string
    {
        return __('Détails du Produit');
    }
    protected ?string $heading = ''; 

    public ?Product $product = null;
    
    public ?int $selectedFolderId = null;
    public ?string $serviceDate = null;
    public ?int $quantity = 1;
    public int $ageSelectorAdults = 1;
    public int $ageSelectorChildren = 0;
    public int $ageSelectorTeenagers = 0;

    public array $selectedOptions = []; 
    public array $customValues = [];

    public ?int $activeAgencyId = null;
    public bool $isAdmin = false;

    protected function notifyRequestDeadlineExceeded(string $serviceDate): void
    {
        $deadline = $this->product?->getAgencyRequestDeadline($serviceDate);

        Notification::make()
            ->title(__('Délai de demande dépassé'))
            ->body(__('Cette prestation devait être demandée au plus tard le :date. Contactez-nous via le formulaire pour connaître les alternatives encore possibles.', [
                'date' => $deadline?->format('d/m/Y'),
            ]))
            ->danger()
            ->persistent()
            ->actions([
                Action::make('contact')
                    ->label(__('Nous contacter'))
                    ->url(route('filament.agency.pages.assistance-sur-mesure')),
            ])
            ->send();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount($record): void
    {
        $user = Filament::auth()->user() ?? auth('agency')->user() ?? auth('web')->user();
        if ($user) {
            if ($user->agency_id) {
                $this->activeAgencyId = (int) $user->agency_id;
            }
            if (($user->hasRole('super_admin') || $user->hasRole('admin'))) {
                $this->isAdmin = true;
            }
        }

        $this->product = Product::with(['productOptions.seasonalPrices', 'productPeriods.productPrices'])->findOrFail($record);

        if (! $this->isAdmin && ! $this->product->is_public) {
            abort(404);
        }

        if (! $this->isAdmin && ! $this->product->isAvailableForAgency($this->activeAgencyId)) {
            abort(403, __('Cette prestation n’est pas disponible pour le groupe de votre agence.'));
        }

        if ($this->product->productOptions) {
            $groupedRequired = [];

            foreach ($this->product->productOptions as $option) {
                $isEnabled = false;

                if ($option->is_required || !empty($option->group_name)) {
                    $groupKey = !empty($option->group_name) ? $option->group_name : 'default_required';
                    if (!isset($groupedRequired[$groupKey])) {
                        $groupedRequired[$groupKey] = true;
                        $isEnabled = true;
                    }
                }

                $this->selectedOptions[$option->id] = [
                    'enabled' => $isEnabled,
                    'quantity' => 1
                ];
            }
        }

        if ($this->product->product_type === 'transport') {
            if (empty($this->customValues['transport_routes'])) {
                $this->customValues['transport_routes'] = [
                    [
                        'departure_station' => '',
                        'arrival_station' => '',
                        'departure_date' => now()->format('Y-m-d'),
                        'departure_time' => '',
                        'train_number' => '',
                        'specific_requests' => '',
                        'option_id' => null,
                        'pax_count' => 1,
                    ]
                ];
            }
        } elseif (!empty($this->product->custom_field_definitions)) {
            foreach ($this->product->custom_field_definitions as $def) {
                $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                $isPerPax = $def['is_per_passenger'] ?? false;
                
                if ($isPerPax) {
                    $this->customValues[$key] = [];
                } else {
                    $this->customValues[$key] = $def['type'] === 'toggle' ? false : null;

                    if (($def['type'] ?? null) === 'client_hotel') {
                        $this->customValues["{$key}_address"] = null;
                        $this->customValues["{$key}_google_maps_url"] = null;
                    }
                }
            }
        }
    }

    public function addTransportRoute(): void
    {
        if (!isset($this->customValues['transport_routes'])) {
            $this->customValues['transport_routes'] = [];
        }
        $this->customValues['transport_routes'][] = [
            'departure_station' => '',
            'arrival_station' => '',
            'departure_date' => now()->format('Y-m-d'),
            'departure_time' => '',
            'train_number' => '',
            'specific_requests' => '',
            'option_id' => null,
            'pax_count' => 1,
        ];
    }

    public function removeTransportRoute(int $index): void
    {
        if (isset($this->customValues['transport_routes'][$index])) {
            unset($this->customValues['transport_routes'][$index]);
            $this->customValues['transport_routes'] = array_values($this->customValues['transport_routes']);
        }
    }

    public function getStationMapUrl(?string $stationName): ?string
    {
        if (!$stationName) return null;

        $stationNameClean = trim($stationName);
        
        $train = TrainStation::where('name_en', $stationNameClean)
            ->orWhere('name_ja', $stationNameClean)
            ->first();

        if ($train && !empty($train->google_maps_url)) {
            return $train->google_maps_url;
        }

        $bus = BusStation::where('name_en', $stationNameClean)
            ->orWhere('name_ja', $stationNameClean)
            ->first();

        if ($bus && !empty($bus->google_maps_url)) {
            return $bus->google_maps_url;
        }

        return null;
    }

    #[Computed]
    public function stationsList()
    {
        $trains = TrainStation::orderBy('importance_score', 'desc')
            ->orderBy('name_en', 'asc')
            ->get(['name_en', 'name_ja', 'city', 'prefecture', 'importance_score', 'google_maps_url'])
            ->map(fn($s) => [
                'name' => $s->name_en,
                'label' => "🚆 {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : "") . ($s->city ? " - {$s->city}" : ($s->prefecture ? " - {$s->prefecture}" : "")),
                'type' => 'train',
                'score' => $s->importance_score ?? 10,
                'maps_url' => $s->google_maps_url
            ]);

        $buses = BusStation::orderBy('name_en', 'asc')
            ->get(['name_en', 'name_ja', 'address', 'google_maps_url'])
            ->map(fn($s) => [
                'name' => $s->name_en,
                'label' => "🚌 [Bus] {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : "") . ($s->address ? " - {$s->address}" : ""),
                'type' => 'bus',
                'score' => 50, // Score d'importance à 50
                'maps_url' => $s->google_maps_url
            ]);

        return $trains->concat($buses)->sortByDesc('score')->values();
    }

    public function selectGroupVariant(string $groupName, $selectedOptionId): void
    {
        if (!$this->product || !$this->product->productOptions) return;

        $selectedOptionId = (int) $selectedOptionId;

        foreach ($this->product->productOptions as $option) {
            $optGroup = !empty($option->group_name) ? $option->group_name : ($option->is_required ? 'default_required' : null);
            if ($optGroup === $groupName) {
                $this->selectedOptions[$option->id]['enabled'] = ($option->id === $selectedOptionId);
            }
        }

        $this->refreshCalendar();
    }

    public function updatedSelectedOptions(): void
    {
        $this->refreshCalendar();
    }

    /** Recalcule le calendrier (jours d'ouverture / prix propres à l'option choisie) et le renvoie au navigateur. */
    protected function refreshCalendar(): void
    {
        $map = $this->getCalendarMapProperty();

        if ($this->serviceDate && ! ($map[$this->serviceDate]['available'] ?? false)) {
            $this->serviceDate = null;
        }

        $this->dispatch('calendar-map-updated', map: $map);
    }

    public function updatedQuantity($value)
    {
        if ($this->product && $this->product->max_pax !== null) {
            if ((int)$value > $this->product->max_pax) {
                $this->quantity = $this->product->max_pax;
                
                $contactUrl = route('contact.index');
                
                Notification::make()
                    ->title(__('Quantité maximale atteinte'))
                    ->body(new HtmlString("Cette prestation est disponible à l'achat rapide pour <strong>{$this->product->max_pax} pax maximum</strong>.<br>Pour une demande sur-mesure, veuillez <a href='{$contactUrl}' style='text-decoration: underline; font-weight: bold;'>nous contacter</a>."))
                    ->warning()
                    ->send();
            }
        }
    }

    public function updatedAgeSelectorAdults($value): void
    {
        $this->ageSelectorAdults = max(0, (int) $value);
        $this->syncAgeSelectorQuantity();
    }

    public function updatedAgeSelectorChildren($value): void
    {
        $this->ageSelectorChildren = max(0, (int) $value);
        $this->syncAgeSelectorQuantity();
    }

    public function updatedAgeSelectorTeenagers($value): void
    {
        $this->ageSelectorTeenagers = max(0, (int) $value);
        $this->syncAgeSelectorQuantity();
    }

    protected function syncAgeSelectorQuantity(): void
    {
        if (!$this->product?->usesAgeSelector()) {
            return;
        }

        if (($this->ageSelectorAdults + $this->ageSelectorChildren + $this->ageSelectorTeenagers) < 1) {
            $this->ageSelectorAdults = 1;
        }

        $this->ageSelectorChildren = $this->product->hasChildSelector() ? $this->ageSelectorChildren : 0;
        $this->ageSelectorTeenagers = $this->product->hasTeenSelector() ? $this->ageSelectorTeenagers : 0;
        $this->quantity = $this->ageSelectorAdults + $this->ageSelectorChildren + $this->ageSelectorTeenagers;
    }

    #[Computed]
    public function foldersList()
    {
        $agencyId = $this->activeAgencyId ?? Filament::auth()->user()?->agency_id;

        if (!$agencyId) return collect();
        
        return Folder::where('agency_id', $agencyId)
            ->whereIn('status', ['draft', 'pending', 'confirmed'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function createFolderAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('createFolder')
            ->label(__('Nouveau dossier'))
            ->icon('heroicon-m-plus')
            ->color('gray')
            ->size('sm')
            ->modalHeading(__('Créer un nouveau dossier'))
            ->modalWidth('4xl')
            ->form([
                Section::make(__('Informations Principales'))->columns(2)->schema([
                    TextInput::make('folder_name')
                        ->label(__('Nom du dossier / Réf. Groupe'))
                        ->required(),

                    TextInput::make('lead_traveler_name')
                        ->label(__('Nom complet du pax principal'))
                        ->required(),

                    TextInput::make('hotel_booking_name')
                        ->label(__('Nom complet réservation hôtel'))
                        ->placeholder(__('Si différent du voyageur principal'))
                        ->columnSpanFull(),

                    DatePicker::make('start_date')
                         ->label(__('Date d\'arrivée au Japon'))
                        ->live()
                        ->required()
                        ->beforeOrEqual('end_date')
                        ->validationMessages([
                            'before_or_equal' => __('L\'arrivée doit être avant ou le jour du départ.'),
                        ]),

                    DatePicker::make('end_date')
                         ->label(__('Date de départ'))
                        ->live()
                        ->required()
                        ->afterOrEqual('start_date')
                        ->validationMessages([
                            'after_or_equal' => __('Le départ doit être après ou le jour de l\'arrivée.'),
                        ])
                        ->minDate(fn (Get $get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null),

                    Repeater::make('contact_phones')
                        ->label(__('Contact du voyageur pendant le séjour'))
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->defaultItems(1)
                        ->schema([
                            TextInput::make('phone')
                                ->label(__('Téléphone'))
                                ->tel()
                                ->required()
                                ->placeholder(__('+33 6...')),
                            TextInput::make('email')
                                ->label(__('Adresse E-mail'))
                                ->email()
                                ->placeholder(__('voyageur@email.com')),
                        ])
                        ->columnSpanFull()
                        ->columns(2),
                ]),

                Section::make(__('Liste des Voyageurs'))->schema([
                    Repeater::make('passengers')
                        ->hiddenLabel()
                        ->addActionLabel(__('Ajouter un voyageur'))
                        ->defaultItems(1)
                        ->schema([
                            Group::make()->schema([
                                TextInput::make('last_name')->label(__('Nom'))->required(),
                                TextInput::make('first_name')->label(__('Prénom'))->required(),
                                 DatePicker::make('birth_date')->label(__('Date de naissance'))->required(),
                                TextInput::make('nationality')->label(__('Nationalité'))->default('Française')->required(),
                            ])->columns(4),
                            
                            Textarea::make('dietary_restrictions')->label(__('Allergies / Restrictions alimentaires'))->rows(1),
                            Textarea::make('mobility_concerns')->label(__('Besoins PMR / Handicap'))->rows(1),
                        ])
                        ->required(),
                ]),

                Section::make(__('Logistique d\'arrivée'))->schema([
                    Textarea::make('flight_info')->label(__('Vols (Arrivée/Départ)'))->rows(3),
                    TextInput::make('first_hotel_name')->label(__('1er Hébergement (Nom)')),

                    DatePicker::make('first_hotel_check_in')
                        ->label(__('Date Check-in 1er Hébergement'))
                        ->live()
                        ->minDate(fn (Get $get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null)
                        ->maxDate(fn (Get $get) => $get('end_date') ? Carbon::parse($get('end_date'))->endOfDay() : null)
                        ->afterOrEqual('start_date')
                        ->beforeOrEqual('end_date')
                        ->validationMessages([
                            'after_or_equal' => __('Le check-in doit avoir lieu après l\'arrivée.'),
                            'before_or_equal' => __('Le check-in doit avoir lieu avant le départ.'),
                        ]),

                    Textarea::make('first_hotel_address')
                        ->label(__('Adresse du premier hébergement'))
                        ->placeholder(__('Adresse complète pour l\'envoi éventuel de documents...'))
                        ->rows(2),
                ])
            ])
            ->action(function (array $data, \Filament\Pages\Page $livewire) {
                $agencyId = $livewire->activeAgencyId ?? Filament::auth()->user()?->agency_id;

                if (!$agencyId) {
                    Notification::make()->title(__('Erreur'))->body('La session agence est expirée ou introuvable.')->danger()->send();
                    return;
                }

                $folder = Folder::create([
                    'agency_id' => $agencyId,
                    'folder_name' => $data['folder_name'],
                    'lead_traveler_name' => $data['lead_traveler_name'],
                    'hotel_booking_name' => $data['hotel_booking_name'] ?? null,
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'contact_phones' => $data['contact_phones'] ?? [],
                    'flight_info' => $data['flight_info'] ?? null,
                    'first_hotel_name' => $data['first_hotel_name'] ?? null,
                    'first_hotel_check_in' => $data['first_hotel_check_in'] ?? null,
                    'first_hotel_address' => $data['first_hotel_address'] ?? null,
                    'status' => 'draft',
                    'total_price' => 0,
                    'folder_fee' => \Filament\Facades\Filament::auth()->user()->agency?->clientGroup?->folder_fee ?? 0,
                    'ticket_dispatch_method' => 'hotel',
                ]);

                if (!empty($data['passengers'])) {
                    $adults = 0;
                    $children = 0;
                    
                    foreach ($data['passengers'] as $pax) {
                        FolderPassenger::create([
                            'folder_id' => $folder->id,
                            'last_name' => $pax['last_name'],
                            'first_name' => $pax['first_name'],
                            'birth_date' => $pax['birth_date'],
                            'nationality' => $pax['nationality'],
                            'dietary_restrictions' => $pax['dietary_restrictions'] ?? null,
                            'mobility_concerns' => $pax['mobility_concerns'] ?? null,
                        ]);
                        
                        if (Carbon::parse($pax['birth_date'])->age >= 12) {
                            $adults++;
                        } else {
                            $children++;
                        }
                    }
                    
                    $folder->update([
                        'pax_adults' => max(1, $adults),
                        'pax_children' => $children,
                    ]);
                }

                $livewire->selectedFolderId = $folder->id;
                
                unset($livewire->foldersList);

                Notification::make()
                    ->title(__('Dossier créé avec succès !'))
                    ->success()
                    ->send();
            });
    }

    public function getCalendarMapProperty(): array
    {
        $map = [];
        if (!$this->product) return $map;

        // Une option peut imposer ses propres jours d'ouverture et sa grille tarifaire
        $enabledIds = collect($this->selectedOptions)->filter(fn ($opt) => ! empty($opt['enabled']))->keys()->all();
        $this->product->applyTariffVariant($enabledIds);
        $optionDays = $this->product->productOptions
            ->whereIn('id', $enabledIds)
            ->pluck('available_days')
            ->first(fn ($days) => ! empty($days));

        $availableDays = $optionDays ?: ($this->product->available_days ?? ['mon','tue','wed','thu','fri','sat','sun']);
        $blackoutDates = collect($this->product->blackout_dates ?? [])->pluck('date')->toArray();
        $isOnDemand = $this->product->is_on_demand;

        $date = Carbon::today();
        $endDate = Carbon::today()->addYears(2);

        while ($date <= $endDate) {
            $dateStr = $date->format('Y-m-d');
            $dayOfWeek = strtolower($date->format('D'));

            $isAvailable = true;
            if (!empty($availableDays) && !in_array($dayOfWeek, $availableDays)) {
                $isAvailable = false;
            }
            if (in_array($dateStr, $blackoutDates)) {
                $isAvailable = false;
            }

            $price = 0;
            if (!$isOnDemand && $isAvailable) {
                $matchedPrice = $this->product->periodForDate($date)?->productPrices
                    ->reject(fn ($price): bool => $price->isExtraPaxRate())
                    ->min('price');
                if ($matchedPrice !== null) {
                    $price = $matchedPrice;
                }
            }

            $map[$dateStr] = [
                'available' => $isAvailable,
                'price' => $price ? number_format($price, 0, '.', ' ') : null,
                'is_on_demand' => $isOnDemand
            ];

            $date->addDay();
        }

        return $map;
    }

    protected array $estimateMemo = [];

    public function getEstimatedPrice(): array
    {
        if (!$this->product) return [];

        $memoKey = md5(json_encode([$this->selectedOptions, $this->customValues['transport_routes'] ?? null, $this->quantity, $this->serviceDate, $this->ageSelectorAdults, $this->ageSelectorChildren, $this->ageSelectorTeenagers]));

        return $this->estimateMemo[$memoKey] ??= $this->computeEstimatedPrice();
    }

    protected function computeEstimatedPrice(): array
    {

        // Une option peut remplacer la grille tarifaire de base (ex: cérémonie privée)
        $this->product->applyTariffVariant(
            collect($this->selectedOptions)->filter(fn ($opt) => ! empty($opt['enabled']))->keys()->all()
        );

        if ($this->product->product_type === 'transport') {
            $routes = $this->customValues['transport_routes'] ?? [];
            $totalBase = 0;
            $totalOptions = 0;
            $totalTickets = 0;
            $unitFeeDisplay = 0;

            foreach ($routes as $route) {
                $pax = intval($route['pax_count'] ?? 1);
                if ($pax < 1) $pax = 1;
                $totalTickets += $pax; // Chaque passager sur un trajet = 1 billet
                
                $rDate = $route['departure_date'] ?? null;
                // 1. Calcul des Frais de service / émission Takada par billet pour ce trajet
                $routeUnitFee = 0;
                if ($period = $this->product->periodForDate($rDate ?? now())) {
                    $validPrices = $period->productPrices->where('min_pax', '<=', $pax)->where('max_pax', '>=', $pax);
                    $routeUnitFee = $validPrices->isNotEmpty()
                        ? $validPrices->first()->price
                        : ($period->productPrices->sortByDesc('max_pax')->first()->price ?? 0);
                }

                if ($unitFeeDisplay === 0 && $routeUnitFee > 0) {
                    $unitFeeDisplay = $routeUnitFee;
                }

                // FRAIS TOTAL DE SERVICE = Frais unitaire x Nombre de passagers (billets) sur ce trajet
                $totalBase += ($routeUnitFee * $pax);

                // 2. Calcul des suppléments de classe / options pour ce trajet
                if (!empty($route['option_id'])) {
                    $opt = $this->product->productOptions->firstWhere('id', (int)$route['option_id']);
                    if ($opt) {
                        $mod = $opt->priceModifierForDate($rDate ?? now()->toDateString(), 'adult', $pax);
                        if ($opt->billing_type === 'per_pax' || $opt->hasExtraPaxSeasonalModifierForDate($rDate ?? now()->toDateString(), $pax)) {
                            $totalOptions += $mod * $opt->billableQuantityForDate($rDate ?? now()->toDateString(), $pax);
                        } else {
                            $totalOptions += $mod;
                        }
                    }
                }
            }

            if ($unitFeeDisplay === 0 && $totalTickets > 0 && $totalBase > 0) {
                $unitFeeDisplay = round($totalBase / $totalTickets);
            }

            $grandTotal = $totalBase + $totalOptions;

            return [
                'is_on_demand' => true,
                'has_date' => true,
                'unit_base' => $unitFeeDisplay,
                'total_base' => $totalBase,
                'total_options' => $totalOptions,
                'grand_total' => $grandTotal,
                'qty' => $totalTickets, // Nombre total de billets
                'route_count' => count($routes)
            ];
        }

        $qty = (int)$this->quantity > 0 ? (int)$this->quantity : 1;
        
        if ($this->product->max_pax && $qty > $this->product->max_pax) {
            $qty = $this->product->max_pax;
        }

        $basePricePerUnit = 0;
        $basePriceRow = null;
        $basePricePeriod = null;
        $ageBaseTotal = null;
        $ageBaseBreakdown = null;
        $hasDate = !empty($this->serviceDate);
        $isOnDemand = $this->product->is_on_demand ?? false;

        if ($hasDate && !$isOnDemand) {
            $matchedPrice = null;
            if ($period = $this->product->periodForDate($this->serviceDate)) {
                $basePricePeriod = $period;
                $validPrices = $period->productPrices->where('min_pax', '<=', $qty)->where('max_pax', '>=', $qty)
                    ->reject(fn ($price) => $price->isExtraPaxRate());
                $basePriceRow = $validPrices->isNotEmpty()
                    ? $validPrices->first()
                    : $period->productPrices->reject(fn ($price) => $price->isExtraPaxRate())->sortByDesc('max_pax')->first();
                $matchedPrice = $basePriceRow?->price ?? 0;
                if ($this->product->usesAgeSelector()) {
                    $ageBaseBreakdown = $this->product->calculateAgeBasedBreakdown($period, $qty, $this->ageSelectorAdults, $this->ageSelectorChildren, $this->ageSelectorTeenagers);
                    $ageBaseTotal = $ageBaseBreakdown['total'] ?? null;
                }
            }
            $basePricePerUnit = $matchedPrice ?? 0;
        } else {
            $minPrice = null;
            $minPriceRow = null;
            $minAgeBaseTotal = null;
            if ($this->product->productPeriods) {
                foreach($this->product->productPeriods as $period) {
                    if ($period->productPrices) {
                    $validPrices = $period->productPrices->where('min_pax', '<=', $qty)->where('max_pax', '>=', $qty)
                        ->reject(fn ($price) => $price->isExtraPaxRate());

                        if ($this->product->usesAgeSelector()) {
                            $candidateAgeBreakdown = $this->product->calculateAgeBasedBreakdown(
                                $period,
                                $qty,
                                $this->ageSelectorAdults,
                                $this->ageSelectorChildren,
                                $this->ageSelectorTeenagers,
                            );

                            if ($candidateAgeBreakdown !== null
                                && ($minAgeBaseTotal === null || $candidateAgeBreakdown['total'] < $minAgeBaseTotal)) {
                                $minAgeBaseTotal = $candidateAgeBreakdown['total'];
                                $ageBaseBreakdown = $candidateAgeBreakdown;
                            }
                        }

                        foreach($validPrices as $price) {
                            $candidateTotal = $this->product->baseRateTotal($price, $qty, $period);
                            if ($minPrice === null || $candidateTotal < $minPrice) {
                                $minPrice = $candidateTotal;
                                $minPriceRow = $price;
                                $basePricePeriod = $period;
                            }
                        }
                    }
                }
            }

            if ($minAgeBaseTotal !== null) {
                $ageBaseTotal = $minAgeBaseTotal;
                $basePricePerUnit = $minAgeBaseTotal / max(1, $qty);
            } else {
                $basePriceRow = $minPriceRow;
                $basePricePerUnit = ($minPrice ?? 0) / max(1, $qty);
            }
        }

        if ($this->product->usesAgeSelector()) {
            $this->ageSelectorAdults = max(1, (int) ($this->ageSelectorAdults ?: $this->quantity ?: 1));
            $this->ageSelectorChildren = max(0, (int) $this->ageSelectorChildren);
            $this->ageSelectorTeenagers = max(0, (int) $this->ageSelectorTeenagers);
            $this->syncAgeSelectorQuantity();
        }

        $totalBase = $ageBaseTotal !== null
            ? $ageBaseTotal
            : $this->product->baseRateTotal($basePriceRow, $qty, $basePricePeriod);
        $basePricePerUnit = $totalBase / max(1, $qty);
        
        $optionsPrice = 0;
        $optionsAdultTotal = 0;
        $optionsChildTotal = 0;
        $optionsTeenTotal = 0;
        $guidingVariantTotal = 0;
        $guidingOptionalTotal = 0;
        $guidingVariantNames = [];
        if ($this->product->productOptions) {
            foreach ($this->product->productOptions as $option) {
                $optData = $this->selectedOptions[$option->id] ?? [];
                if (!empty($optData['enabled'])) {
                    $pricingQuantity = $this->product->enable_age_selector ? $this->ageSelectorAdults : $qty;
                    $mod = $option->priceModifierForDate($this->serviceDate, 'adult', $pricingQuantity);
                    $optionTotal = 0;
                    if ($option->billing_type === 'per_pax' || $option->hasExtraPaxSeasonalModifierForDate($this->serviceDate, $pricingQuantity)) {
                        if ($this->product->enable_age_selector) {
                            $billableGroups = $option->billableAgeGroupQuantitiesForDate(
                                $this->serviceDate,
                                $qty,
                                $this->ageSelectorAdults,
                                $this->ageSelectorChildren,
                                $this->ageSelectorTeenagers,
                            );
                            $adultTotal = $option->priceModifierForDate($this->serviceDate, 'adult', $this->ageSelectorAdults) * $billableGroups['adults'];
                            $childTotal = $option->priceModifierForDate($this->serviceDate, 'child', $this->ageSelectorChildren) * $billableGroups['children'];
                            $teenTotal = $option->priceModifierForDate($this->serviceDate, 'teen', $this->ageSelectorTeenagers) * $billableGroups['teenagers'];
                            $optionsAdultTotal += $adultTotal;
                            $optionsChildTotal += $childTotal;
                            $optionsTeenTotal += $teenTotal;
                            $optionTotal = $adultTotal + $childTotal + $teenTotal;
                        } else {
                            $optionTotal = $mod * $option->billableQuantityForDate($this->serviceDate, $qty);
                        }
                    } elseif ($option->billing_type === 'per_booking') {
                        $optionTotal = $mod;
                    } elseif ($option->billing_type === 'manual') {
                        $optQty = (int)($optData['quantity'] ?? 1);
                        $optionTotal = $mod * $optQty;
                    }

                    $optionsPrice += $optionTotal;

                    if ((int) $this->product->category_id === 4) {
                        if ($option->is_required || filled($option->group_name)) {
                            $guidingVariantTotal += $optionTotal;
                            $guidingVariantNames[] = $option->name;
                        } else {
                            $guidingOptionalTotal += $optionTotal;
                        }
                    }
                }
            }
        }

        $guidingPaxLabel = null;
        if ($basePriceRow) {
            $guidingPaxLabel = $basePriceRow->min_pax === $basePriceRow->max_pax
                ? __(':count pax', ['count' => $basePriceRow->min_pax])
                : __(':min–:max pax', ['min' => $basePriceRow->min_pax, 'max' => $basePriceRow->max_pax]);
        }

        return [
            'is_on_demand' => $isOnDemand,
            'has_date' => $hasDate,
            'unit_base' => $ageBaseTotal !== null ? ($ageBaseTotal / max(1, $qty)) : $basePricePerUnit,
            'total_base' => $totalBase,
            'base_is_global' => $this->product->usesGlobalRate($basePriceRow),
            'base_adult_unit' => $ageBaseBreakdown['adult_unit'] ?? null,
            'base_child_unit' => $ageBaseBreakdown['child_unit'] ?? null,
            'base_teen_unit' => $ageBaseBreakdown['teen_unit'] ?? null,
            'base_adult_total' => $ageBaseBreakdown['adult_total'] ?? null,
            'base_child_total' => $ageBaseBreakdown['child_total'] ?? null,
            'base_teen_total' => $ageBaseBreakdown['teen_total'] ?? null,
            'total_options' => $optionsPrice,
            'options_adult_total' => $optionsAdultTotal,
            'options_child_total' => $optionsChildTotal,
            'options_teen_total' => $optionsTeenTotal,
            'is_guiding' => (int) $this->product->category_id === 4,
            'guiding_variant_names' => $guidingVariantNames,
            'guiding_variant_total' => $guidingVariantTotal,
            'guiding_optional_total' => $guidingOptionalTotal,
            'guiding_season_name' => $basePricePeriod?->name,
            'guiding_pax_label' => $guidingPaxLabel,
            'grand_total' => $totalBase + $optionsPrice,
            'qty' => $qty
        ];
    }

    public function addToFolder(): void
    {
        $agencyId = $this->activeAgencyId ?? Filament::auth()->user()?->agency_id;

        if (!$agencyId) {
            Notification::make()->title(__('Session Expirée'))->danger()->send();
            return;
        }

        $folder = Folder::query()
            ->whereKey($this->selectedFolderId)
            ->where('agency_id', $agencyId)
            ->first();

        if (! $folder) {
            Notification::make()
                ->title(__('Dossier introuvable'))
                ->body(__('Le dossier sélectionné n’est pas accessible à votre agence.'))
                ->danger()
                ->send();

            return;
        }

        if ($this->product && $this->product->product_type === 'transport') {
            $rules = [
                'selectedFolderId' => 'required|exists:folders,id',
                'customValues.transport_routes' => 'required|array|min:1',
                'customValues.transport_routes.*.departure_station' => 'required|string',
                'customValues.transport_routes.*.arrival_station' => 'required|string',
                'customValues.transport_routes.*.departure_date' => 'required|date',
                'customValues.transport_routes.*.pax_count' => 'required|integer|min:1',
                'customValues.transport_routes.*.option_id' => 'required',
                'customValues.transport_routes.*.specific_requests' => 'nullable|string|max:5000',
            ];

            $messages = [
                'selectedFolderId.required' => __('Veuillez sélectionner un dossier de voyage.'),
                'customValues.transport_routes.required' => __('Au moins un trajet est requis.'),
                'customValues.transport_routes.*.departure_station.required' => __('Le point de départ est obligatoire.'),
                'customValues.transport_routes.*.arrival_station.required' => __('Le point d\'arrivée est obligatoire.'),
                'customValues.transport_routes.*.departure_date.required' => __('La date du trajet est obligatoire.'),
                'customValues.transport_routes.*.option_id.required' => __('Veuillez sélectionner une classe'),
            ];

            try {
                $this->validate($rules, $messages);
            } catch (\Illuminate\Validation\ValidationException $e) {
                Notification::make()
                    ->title(__('Informations manquantes'))
                    ->body(__('Veuillez renseigner tous les détails de vos trajets avant de valider.'))
                    ->danger()
                    ->send();
                throw $e;
            }

            $routes = $this->customValues['transport_routes'] ?? [];
            $firstRoute = $routes[0] ?? null;
            $serviceDate = !empty($firstRoute['departure_date']) ? $firstRoute['departure_date'] : now()->format('Y-m-d');
            $quantity = !empty($firstRoute['pax_count']) ? (int)$firstRoute['pax_count'] : 1;

            foreach ($routes as $route) {
                $departureDate = $route['departure_date'] ?? null;

                if ($departureDate && $this->product->isAgencyRequestDeadlineExceeded($departureDate)) {
                    $this->notifyRequestDeadlineExceeded($departureDate);

                    return;
                }
            }

            $status = \App\Models\ItemStatus::firstOrCreate(
                ['name' => 'En attente de validation'],
                ['color' => 'warning']
            );

            $folderItem = FolderItem::create([
                'folder_id' => $folder->id,
                'product_id' => $this->product->id,
                'service_date' => $serviceDate,
                'quantity' => $quantity,
                'selected_options' => [],
                'custom_values' => ['transport_routes' => $routes],
                'item_status_id' => $status->id,
                'unit_price' => 0,                            
                'total_price' => 0,
            ]);

            \App\Filament\Resources\Folders\FolderResource::updateItemPrices(
                function($k, $v) use ($folderItem) { $folderItem->update([$k => $v]); },
                function($k) use ($folderItem) { return $folderItem->{$k}; }
            );

            Notification::make()
                ->title(__('Demande de trajet ajoutée au dossier !'))
                ->body(__('Vos trajets ont été enregistrés. Notre équipe va établir l\'itinéraire optimal.'))
                ->success()
                ->send();

            $this->reset(['serviceDate', 'quantity', 'selectedFolderId', 'ageSelectorAdults', 'ageSelectorChildren', 'ageSelectorTeenagers']);
            $this->mount($this->product->id);
            return;
        }

        $rules = [
            'selectedFolderId' => 'required|exists:folders,id',
            'serviceDate' => 'required|date',
            'quantity' => 'required|integer|min:1',
        ];

        if ($this->product?->usesAgeSelector()) {
            $rules['ageSelectorAdults'] = 'required|integer|min:0';
            if ($this->product->hasChildSelector()) {
                $rules['ageSelectorChildren'] = 'required|integer|min:0';
            }
            if ($this->product->hasTeenSelector()) {
                $rules['ageSelectorTeenagers'] = 'required|integer|min:0';
            }
            $this->syncAgeSelectorQuantity();
        }

        $messages = [
            'selectedFolderId.required' => __('Veuillez sélectionner un dossier de voyage.'),
            'serviceDate.required' => __('La date de la prestation est obligatoire.'),
            'quantity.required' => __('Le nombre de personnes est requis.'),
            'quantity.min' => __('Il faut au minimum 1 personne.'),
        ];

        if ($this->product && $this->product->max_pax) {
            $rules['quantity'] .= '|max:' . $this->product->max_pax;
            $messages['quantity.max'] = __('Cette prestation est limitée à :max participants au maximum.', ['max' => $this->product->max_pax]);
        }

        $groupedRequired = $this->product->productOptions
            ->filter(fn($o) => $o->is_required || !empty($o->group_name))
            ->groupBy(fn($o) => !empty($o->group_name) ? $o->group_name : 'default_required');

        foreach ($groupedRequired as $groupName => $optionsGroup) {
            $selectedCount = $optionsGroup
                ->filter(fn ($opt) => !empty($this->selectedOptions[$opt->id]['enabled']))
                ->count();
            $hasChoiceSelected = $selectedCount === 1;

            if (!$hasChoiceSelected) {
                $displayName = ($groupName === 'default_required') ? __('Déclinaison') : $groupName;
                Notification::make()
                    ->title(__('Déclinaison obligatoire manquante'))
                    ->body(__('Veuillez sélectionner un choix pour :name.', ['name' => $displayName]))
                    ->danger()
                    ->send();

                return;
            }
        }

        $qty = (int)$this->quantity > 0 ? (int)$this->quantity : 1;

        if (!empty($this->product->custom_field_definitions)) {
            foreach ($this->product->custom_field_definitions as $def) {
                $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                $isRequired = $def['is_required'] ?? false;
                $isPerPax = $def['is_per_passenger'] ?? false;
                $isFileType = ($def['type'] ?? '') === 'file';
                $baseRule = $isFileType ? 'file|max:10240' : '';

                if ($isRequired && $def['type'] !== 'toggle') {
                    if ($isPerPax) {
                        for ($i = 0; $i < $qty; $i++) {
                            $rules["customValues.{$key}.{$i}"] = $baseRule ? "required|{$baseRule}" : 'required';
                            $messages["customValues.{$key}.{$i}.required"] = "Le champ '{$def['name']}' (Pax " . ($i + 1) . ") est requis.";
                        }
                    } else {
                        $rules["customValues.{$key}"] = $baseRule ? "required|{$baseRule}" : 'required';
                        $messages["customValues.{$key}.required"] = "Le champ '{$def['name']}' est requis.";
                    }
                } elseif ($baseRule) {
                    if ($isPerPax) {
                        for ($i = 0; $i < $qty; $i++) {
                            $rules["customValues.{$key}.{$i}"] = "nullable|{$baseRule}";
                        }
                    } else {
                        $rules["customValues.{$key}"] = "nullable|{$baseRule}";
                    }
                }

                if ($isPerPax && isset($this->customValues[$key]) && is_array($this->customValues[$key])) {
                    $this->customValues[$key] = array_slice($this->customValues[$key], 0, $qty);
                }
            }
        }

        if ($this->product?->usesAgeSelector()) {
            $this->customValues['_age_selector'] = [
                'adults' => $this->ageSelectorAdults,
                'children' => $this->ageSelectorChildren,
                'teenagers' => $this->ageSelectorTeenagers,
                'total' => $this->quantity,
                'child_age_limit' => (int) ($this->product->child_age_limit ?? 11),
                'teen_age_limit' => $this->product->teen_age_limit ? (int) $this->product->teen_age_limit : null,
            ];
        }

        try {
            $this->validate($rules, $messages);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title(__('Informations manquantes'))
                ->body(__('Veuillez vérifier les champs en rouge avant d\'ajouter au dossier.'))
                ->danger()
                ->send();
                
            throw $e;
        }

        if ($this->product->isAgencyRequestDeadlineExceeded($this->serviceDate)) {
            $this->notifyRequestDeadlineExceeded($this->serviceDate);

            return;
        }

        if (!empty($this->product->custom_field_definitions)) {
            foreach ($this->product->custom_field_definitions as $def) {
                $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                $isPerPax = $def['is_per_passenger'] ?? false;

                if (($def['type'] ?? '') === 'file') {
                    if ($isPerPax) {
                        for ($i = 0; $i < $qty; $i++) {
                            if (isset($this->customValues[$key][$i]) && $this->customValues[$key][$i] instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                $this->customValues[$key][$i] = $this->customValues[$key][$i]->store('folders/custom_fields', 'public');
                            }
                        }
                    } else {
                        if (isset($this->customValues[$key]) && $this->customValues[$key] instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                            $this->customValues[$key] = $this->customValues[$key]->store('folders/custom_fields', 'public');
                        }
                    }
                }
            }
        }

        if ($folder->start_date && $folder->end_date) {
            $serviceDateObj = Carbon::parse($this->serviceDate)->startOfDay();
            $folderStart = Carbon::parse($folder->start_date)->startOfDay();
            $folderEnd = Carbon::parse($folder->end_date)->startOfDay();

            if ($serviceDateObj->lt($folderStart) || $serviceDateObj->gt($folderEnd)) {
                Notification::make()
                    ->title(__('Date hors séjour'))
                ->body(__('La date choisie (:date) est en dehors des dates du dossier sélectionné (du :start au :end).', ['date' => $serviceDateObj->format('d/m/Y'), 'start' => $folderStart->format('d/m/Y'), 'end' => $folderEnd->format('d/m/Y')]))
                    ->danger()
                    ->send();
                return; 
            }
        }

        $formattedOptions = [];
        foreach ($this->selectedOptions as $optionId => $data) {
            if ($data['enabled']) {
                $formattedOptions[] = [
                    'product_option_id' => $optionId,
                    'quantity' => $data['quantity']
                ];
            }
        }

        $status = \App\Models\ItemStatus::firstOrCreate(
            ['name' => 'En attente de validation'],
            ['color' => 'warning']
        );

        $folderItem = FolderItem::create([
            'folder_id' => $folder->id,
            'product_id' => $this->product->id,
            'service_date' => $this->serviceDate,
            'quantity' => $this->quantity,
            'pax_adults' => $this->product->enable_age_selector ? $this->ageSelectorAdults : null,
            'pax_children' => $this->product->enable_age_selector ? $this->ageSelectorChildren : null,
            'pax_teenagers' => $this->product->enable_age_selector ? $this->ageSelectorTeenagers : null,
            'selected_options' => $formattedOptions, 
            'custom_values' => $this->customValues,
            'item_status_id' => $status->id,
            'unit_price' => 0,                            
            'total_price' => 0,
        ]);

        \App\Filament\Resources\Folders\FolderResource::updateItemPrices(
            function($k, $v) use ($folderItem) { $folderItem->update([$k => $v]); },
            function($k) use ($folderItem) { return $folderItem->{$k}; }
        );

        Notification::make()
            ->title(__('Demande ajoutée au dossier !'))
                ->body(__('La prestation ainsi que ses options et configurations logistiques ont été enregistrées avec le statut "En attente de validation".'))
            ->success()
            ->send();

        $this->reset(['serviceDate', 'quantity', 'selectedFolderId', 'ageSelectorAdults', 'ageSelectorChildren', 'ageSelectorTeenagers']);
        $this->mount($this->product->id);
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/products/{record}';
    }
}
