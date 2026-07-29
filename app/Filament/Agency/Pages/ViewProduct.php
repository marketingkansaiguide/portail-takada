<?php

namespace App\Filament\Agency\Pages;

use App\Models\Product;
use App\Models\Folder;
use App\Models\FolderItem;
use App\Models\FolderPassenger;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Panel;
use BackedEnum;
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
use Livewire\WithFileUploads; // 💡 IMPORT NÉCESSAIRE POUR LES FICHIERS

class ViewProduct extends Page
{
    use WithFileUploads; // 💡 ACTIVATION DE L'UPLOAD LIVEWIRE

    protected static string|BackedEnum|null $navigationIcon = null; 

    protected string $view = 'filament.agency.pages.view-product';
    protected static ?string $title = 'Détails du Produit';
    protected ?string $heading = ''; 

    public ?Product $product = null;
    
    public ?int $selectedFolderId = null;
    public ?string $serviceDate = null;
    public ?int $quantity = 1;

    public array $selectedOptions = []; 
    public array $customValues = [];

    public ?int $activeAgencyId = null;
    public bool $isAdmin = false;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount($record): void
    {
        $this->product = Product::with(['productOptions', 'productPeriods.productPrices'])->findOrFail($record);

        $user = Filament::auth()->user() ?? auth('agency')->user() ?? auth('web')->user();
        if ($user) {
            if ($user->agency_id) {
                $this->activeAgencyId = (int) $user->agency_id;
            }
            if (in_array($user->role, ['super_admin', 'admin'])) {
                $this->isAdmin = true;
            }
        }

        if (isset($this->product->is_public) && !$this->product->is_public) {
            if (!$this->activeAgencyId) {
                abort(403, 'Cette activité requiert un compte partenaire B2B privilégié.');
            }
        }

        if ($this->product->productOptions) {
            foreach ($this->product->productOptions as $option) {
                $this->selectedOptions[$option->id] = ['enabled' => false, 'quantity' => 1];
            }
        }

        if (!empty($this->product->custom_field_definitions)) {
            foreach ($this->product->custom_field_definitions as $def) {
                $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                $isPerPax = $def['is_per_passenger'] ?? false;
                
                if ($isPerPax) {
                    $this->customValues[$key] = [];
                } else {
                    // Les fichiers doivent commencer avec une valeur nulle, pas un string vide
                    $this->customValues[$key] = $def['type'] === 'toggle' ? false : null; 
                }
            }
        }
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
            ->label('Nouveau dossier')
            ->icon('heroicon-m-plus')
            ->color('gray')
            ->size('sm')
            ->modalHeading('Créer un nouveau dossier')
            ->modalWidth('4xl')
            ->form([
                Section::make('Informations Principales')->columns(2)->schema([
                    TextInput::make('folder_name')
                        ->label('Nom du dossier / Réf. Groupe')
                        ->required(),

                    TextInput::make('lead_traveler_name')
                        ->label('Nom du voyageur principal')
                        ->required(),

                    TextInput::make('hotel_booking_name')
                        ->label('Nom réservation hôtel')
                        ->placeholder('Si différent du voyageur principal')
                        ->columnSpanFull(),

                    DatePicker::make('start_date')
                        ->label('Date d\'arrivée au Japon')
                        ->live()
                        ->required()
                        ->beforeOrEqual('end_date')
                        ->validationMessages([
                            'before_or_equal' => 'L\'arrivée doit être avant ou le jour du départ.',
                        ]),

                    DatePicker::make('end_date')
                        ->label('Date de départ')
                        ->live()
                        ->required()
                        ->afterOrEqual('start_date')
                        ->validationMessages([
                            'after_or_equal' => 'Le départ doit être après ou le jour de l\'arrivée.',
                        ])
                        ->minDate(fn (Get $get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null),

                    Repeater::make('contact_phones')
                        ->label('Contact du voyageur pendant le séjour')
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
                                ->placeholder('voyageur@email.com'),
                        ])
                        ->columnSpanFull()
                        ->columns(2),
                ]),

                Section::make('Liste des Voyageurs')->schema([
                    Repeater::make('passengers')
                        ->hiddenLabel()
                        ->addActionLabel('Ajouter un voyageur')
                        ->defaultItems(1)
                        ->schema([
                            Group::make()->schema([
                                TextInput::make('last_name')->label('Nom')->required(),
                                TextInput::make('first_name')->label('Prénom')->required(),
                                DatePicker::make('birth_date')->label('Date de naissance')->required(),
                                TextInput::make('nationality')->label('Nationalité')->default('Française')->required(),
                            ])->columns(4),
                            
                            Textarea::make('dietary_restrictions')->label('Allergies / Restrictions alimentaires')->rows(1),
                            Textarea::make('mobility_concerns')->label('Besoins PMR / Handicap')->rows(1),
                        ])
                        ->required(),
                ]),

                Section::make('Logistique d\'arrivée')->schema([
                    Textarea::make('flight_info')->label('Vols (Arrivée/Départ)')->rows(3),
                    TextInput::make('first_hotel_name')->label('1er Hôtel (Nom)'),

                    DatePicker::make('first_hotel_check_in')
                        ->label('Date Check-in 1er Hôtel')
                        ->live()
                        ->minDate(fn (Get $get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null)
                        ->maxDate(fn (Get $get) => $get('end_date') ? Carbon::parse($get('end_date'))->endOfDay() : null)
                        ->afterOrEqual('start_date')
                        ->beforeOrEqual('end_date')
                        ->validationMessages([
                            'after_or_equal' => 'Le check-in doit avoir lieu après l\'arrivée.',
                            'before_or_equal' => 'Le check-in doit avoir lieu avant le départ.',
                        ]),

                    Textarea::make('first_hotel_address')
                        ->label('Adresse du premier hôtel')
                        ->placeholder('Adresse complète pour l\'envoi éventuel de documents...')
                        ->rows(2),
                ])
            ])
            ->action(function (array $data, \Filament\Pages\Page $livewire) {
                $agencyId = $livewire->activeAgencyId ?? Filament::auth()->user()?->agency_id;

                if (!$agencyId) {
                    Notification::make()->title('Erreur')->body('La session agence est expirée ou introuvable.')->danger()->send();
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
                    'folder_fee' => 0,
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
                    ->title('Dossier créé avec succès !')
                    ->success()
                    ->send();
            });
    }

    public function getCalendarMapProperty(): array
    {
        $map = [];
        if (!$this->product) return $map;

        $availableDays = $this->product->available_days ?? ['mon','tue','wed','thu','fri','sat','sun'];
        $blackoutDates = collect($this->product->blackout_dates ?? [])->pluck('date')->toArray();
        $isOnDemand = $this->product->is_on_demand;

        $date = Carbon::today();
        $endDate = Carbon::today()->addYears(2);

        while ($date <= $endDate) {
            $dateStr = $date->format('Y-m-d');
            $mdStr = $date->format('m-d');
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
                $matchedPrice = null;
                foreach ($this->product->productPeriods as $period) {
                    if (!$period->start_date || !$period->end_date) continue;
                    $inPeriod = false;
                    if ($period->start_date <= $period->end_date) {
                        $inPeriod = ($mdStr >= $period->start_date && $mdStr <= $period->end_date);
                    } else {
                        $inPeriod = ($mdStr >= $period->start_date || $mdStr <= $period->end_date);
                    }
                    if ($inPeriod) {
                        $minP = $period->productPrices->min('price');
                        if ($minP !== null) {
                            $matchedPrice = $minP;
                            break;
                        }
                    }
                }
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

    public function getEstimatedPrice(): array
    {
        if (!$this->product) return [];

        $qty = (int)$this->quantity > 0 ? (int)$this->quantity : 1;
        
        if ($this->product->max_pax && $qty > $this->product->max_pax) {
            $qty = $this->product->max_pax;
        }

        $basePricePerUnit = 0;
        $hasDate = !empty($this->serviceDate);
        $isOnDemand = $this->product->is_on_demand ?? false;

        if ($hasDate && !$isOnDemand) {
            $mdStr = Carbon::parse($this->serviceDate)->format('m-d');
            $matchedPrice = null;
            
            if ($this->product->productPeriods) {
                foreach ($this->product->productPeriods as $period) {
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
            $basePricePerUnit = $matchedPrice ?? 0;
        } else {
            $minPrice = null;
            if ($this->product->productPeriods) {
                foreach($this->product->productPeriods as $period) {
                    if ($period->productPrices) {
                        $validPrices = $period->productPrices->where('min_pax', '<=', $qty)->where('max_pax', '>=', $qty);
                        foreach($validPrices as $price) {
                            if ($minPrice === null || $price->price < $minPrice) {
                                $minPrice = $price->price;
                            }
                        }
                    }
                }
            }
            $basePricePerUnit = $minPrice ?? 0;
        }

        $totalBase = $basePricePerUnit * $qty;
        
        $optionsPrice = 0;
        if ($this->product->productOptions) {
            foreach ($this->product->productOptions as $option) {
                $optData = $this->selectedOptions[$option->id] ?? [];
                if (!empty($optData['enabled'])) {
                    $mod = $option->price_modifier ?? 0;
                    if ($option->billing_type === 'per_pax') {
                        $optionsPrice += $mod * $qty;
                    } elseif ($option->billing_type === 'per_booking') {
                        $optionsPrice += $mod;
                    } elseif ($option->billing_type === 'manual') {
                        $optQty = (int)($optData['quantity'] ?? 1);
                        $optionsPrice += $mod * $optQty;
                    }
                }
            }
        }

        return [
            'is_on_demand' => $isOnDemand,
            'has_date' => $hasDate,
            'unit_base' => $basePricePerUnit,
            'total_base' => $totalBase,
            'total_options' => $optionsPrice,
            'grand_total' => $totalBase + $optionsPrice,
            'qty' => $qty
        ];
    }

    public function addToFolder(): void
    {
        $agencyId = $this->activeAgencyId ?? Filament::auth()->user()?->agency_id;

        if (!$agencyId) {
            Notification::make()->title('Session Expirée')->danger()->send();
            return;
        }

        $rules = [
            'selectedFolderId' => 'required|exists:folders,id',
            'serviceDate' => 'required|date',
            'quantity' => 'required|integer|min:1',
        ];

        $messages = [
            'selectedFolderId.required' => 'Veuillez sélectionner un dossier de voyage.',
            'serviceDate.required' => 'La date de la prestation est obligatoire.',
            'quantity.required' => 'Le nombre de personnes est requis.',
            'quantity.min' => 'Il faut au minimum 1 personne.',
        ];

        if ($this->product && $this->product->max_pax) {
            $rules['quantity'] .= '|max:' . $this->product->max_pax;
            $messages['quantity.max'] = 'Cette prestation est limitée à ' . $this->product->max_pax . ' participants au maximum.';
        }

        $qty = (int)$this->quantity > 0 ? (int)$this->quantity : 1;

        // 💡 MODIFICATION CLÉ : Validation des fichiers (10Mo max)
        if (!empty($this->product->custom_field_definitions)) {
            foreach ($this->product->custom_field_definitions as $def) {
                $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                $isRequired = $def['is_required'] ?? false;
                $isPerPax = $def['is_per_passenger'] ?? false;

                $baseRule = (($def['type'] ?? '') === 'file') ? 'file|max:10240' : '';

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
                } else {
                    if ($baseRule) {
                        if ($isPerPax) {
                            for ($i = 0; $i < $qty; $i++) {
                                $rules["customValues.{$key}.{$i}"] = "nullable|{$baseRule}";
                            }
                        } else {
                            $rules["customValues.{$key}"] = "nullable|{$baseRule}";
                        }
                    }
                }

                if ($isPerPax && isset($this->customValues[$key]) && is_array($this->customValues[$key])) {
                    $this->customValues[$key] = array_slice($this->customValues[$key], 0, $qty);
                }
            }
        }

        try {
            $this->validate($rules, $messages);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Informations manquantes')
                ->body('Veuillez vérifier les champs en rouge avant d\'ajouter au dossier.')
                ->danger()
                ->send();
                
            throw $e;
        }

        // 💡 MODIFICATION CLÉ : Déplacement physique des fichiers vers le stockage final public
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

        $folder = Folder::find($this->selectedFolderId);
        if ($folder && $folder->start_date && $folder->end_date) {
            $serviceDateObj = Carbon::parse($this->serviceDate)->startOfDay();
            $folderStart = Carbon::parse($folder->start_date)->startOfDay();
            $folderEnd = Carbon::parse($folder->end_date)->startOfDay();

            if ($serviceDateObj->lt($folderStart) || $serviceDateObj->gt($folderEnd)) {
                Notification::make()
                    ->title('Date hors séjour')
                    ->body("La date choisie (" . $serviceDateObj->format('d/m/Y') . ") est en dehors des dates du dossier sélectionné (du " . $folderStart->format('d/m/Y') . " au " . $folderEnd->format('d/m/Y') . ").")
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
            'folder_id' => $this->selectedFolderId,
            'product_id' => $this->product->id,
            'service_date' => $this->serviceDate,
            'quantity' => $this->quantity,
            'selected_options' => $formattedOptions, 
            'custom_values' => $this->customValues,
            'item_status_id' => $status->id,
            'unit_price' => 0,                            
            'total_price' => 0,
        ]);

        if ($folder) {
            \App\Filament\Resources\Folders\FolderResource::updateItemPrices(
                function($k, $v) use ($folderItem) { $folderItem->update([$k => $v]); },
                function($k) use ($folderItem) { return $folderItem->{$k}; }
            );
        }

        Notification::make()
            ->title('Demande ajoutée au dossier !')
            ->body('La prestation ainsi que ses options et configurations logistiques ont été enregistrées avec le statut "En attente de validation".')
            ->success()
            ->send();

        $this->reset(['serviceDate', 'quantity', 'selectedFolderId']);
        $this->mount($this->product->id);
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/products/{record}';
    }
}