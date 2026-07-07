<?php

namespace App\Filament\Agency\Pages;

use App\Models\Product;
use App\Models\Folder;
use App\Models\FolderItem;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Panel;
use BackedEnum;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ViewProduct extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null; 

    protected string $view = 'filament.agency.pages.view-product';
    protected static ?string $title = 'Détails du Produit';
    protected ?string $heading = ''; 

    public ?Product $product = null;
    
    public ?string $selectedFolderId = null;
    public ?string $serviceDate = null;
    public ?int $quantity = 1;

    public array $selectedOptions = []; 
    public array $customValues = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount($record): void
    {
        $this->product = Product::with(['productOptions', 'productPeriods.productPrices'])->findOrFail($record);

        if (isset($this->product->is_public) && !$this->product->is_public) {
            if (!auth('agency')->check()) {
                abort(403, 'Cette activité requiert un compte partenaire privilégié.');
            }
        }

        if ($this->product->productOptions) {
            foreach ($this->product->productOptions as $option) {
                $this->selectedOptions[$option->id] = [
                    'enabled' => false,
                    'quantity' => 1
                ];
            }
        }

        if (!empty($this->product->custom_field_definitions)) {
            foreach ($this->product->custom_field_definitions as $def) {
                $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                $isPerPax = $def['is_per_passenger'] ?? false;
                
                if ($isPerPax) {
                    $this->customValues[$key] = [];
                } else {
                    $this->customValues[$key] = $def['type'] === 'toggle' ? false : '';
                }
            }
        }
    }

    public function getAvailableFoldersProperty()
    {
        if (!auth('agency')->check()) return [];
        
        return Folder::where('agency_id', auth('agency')->user()->agency_id)
            ->whereIn('status', ['draft', 'pending'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getCalendarMapProperty(): array
    {
        $map = [];
        if (!$this->product) return $map;

        $availableDays = $this->product->available_days ?? ['mon','tue','wed','thu','fri','sat','sun'];
        $blackoutDates = collect($this->product->blackout_dates ?? [])->pluck('date')->toArray();
        $defaultPrice = $this->product->price;
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

            $price = $defaultPrice;
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

    public function addToFolder(): void
    {
        if (!auth('agency')->check()) {
            $this->redirect(route('filament.agency.auth.login'));
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
        ];

        $qty = (int)$this->quantity > 0 ? (int)$this->quantity : 1;

        if (!empty($this->product->custom_field_definitions)) {
            foreach ($this->product->custom_field_definitions as $def) {
                $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                $isRequired = $def['is_required'] ?? false;
                $isPerPax = $def['is_per_passenger'] ?? false;

                if ($isRequired && $def['type'] !== 'toggle') {
                    if ($isPerPax) {
                        for ($i = 0; $i < $qty; $i++) {
                            $rules["customValues.{$key}.{$i}"] = 'required';
                            // 💡 CORRECTION : Utilisation de "Pax" dans le message d'erreur
                            $messages["customValues.{$key}.{$i}.required"] = "Le champ '{$def['name']}' (Pax " . ($i + 1) . ") est requis.";
                        }
                    } else {
                        $rules["customValues.{$key}"] = 'required';
                        $messages["customValues.{$key}.required"] = "Le champ '{$def['name']}' est requis.";
                    }
                }

                if ($isPerPax && isset($this->customValues[$key]) && is_array($this->customValues[$key])) {
                    $this->customValues[$key] = array_slice($this->customValues[$key], 0, $qty);
                }
            }
        }

        $this->validate($rules, $messages);

        $formattedOptions = [];
        foreach ($this->selectedOptions as $optionId => $data) {
            if ($data['enabled']) {
                $formattedOptions[] = [
                    'product_option_id' => $optionId,
                    'quantity' => $data['quantity']
                ];
            }
        }

        $folderItem = FolderItem::create([
            'folder_id' => $this->selectedFolderId,
            'product_id' => $this->product->id,
            'service_date' => $this->serviceDate,
            'quantity' => $this->quantity,
            'selected_options' => $formattedOptions, 
            'custom_values' => $this->customValues,       
            'item_status_id' => 1,                        
            'unit_price' => 0,                            
            'total_price' => 0,
        ]);

        $folder = Folder::find($this->selectedFolderId);
        if ($folder) {
            \App\Filament\Resources\Folders\FolderResource::updateItemPrices(
                function($k, $v) use ($folderItem) { $folderItem->update([$k => $v]); },
                function($k) use ($folderItem) { return $folderItem->{$k}; }
            );
        }

        Notification::make()
            ->title('Demande ajoutée au dossier !')
            ->description('La prestation ainsi que ses options et configurations logistiques ont été enregistrées.')
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