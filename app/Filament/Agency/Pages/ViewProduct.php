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

class ViewProduct extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null; 

    protected string $view = 'filament.agency.pages.view-product';

    protected static ?string $title = 'Détails du Produit';

    public ?Product $product = null;
    
    // Données principales de réservation
    public ?string $selectedFolderId = null;
    public ?string $serviceDate = null;
    public ?int $quantity = 1;

    // 💡 RÉINTÉGRATION : Stockage des options et des champs personnalisés requis
    public array $selectedOptions = []; 
    public array $customValues = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount($record): void
    {
        $this->product = Product::with('options')->findOrFail($record);

        // Sécurité produit privé
        if (isset($this->product->is_public) && !$this->product->is_public) {
            if (!auth('agency')->check()) {
                abort(403, 'Cette activité requiert un compte partenaire privilégié.');
            }
        }

        // Pré-remplir le tableau des options et des champs personnalisés pour éviter les erreurs d'index undefined
        if ($this->product->options) {
            foreach ($this->product->options as $option) {
                $this->selectedOptions[$option->id] = [
                    'enabled' => false,
                    'quantity' => 1
                ];
            }
        }

        if (!empty($this->product->custom_field_definitions)) {
            foreach ($this->product->custom_field_definitions as $def) {
                $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                $this->customValues[$key] = $def['type'] === 'toggle' ? false : '';
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

    /**
     * Traitement de la commande avec réintégration de toutes les métadonnées
     */
    public function addToFolder(): void
    {
        if (!auth('agency')->check()) {
            $this->redirect(route('filament.agency.auth.login'));
            return;
        }

        // 1. Validation des champs de base
        $rules = [
            'selectedFolderId' => 'required|exists:folders,id',
            'serviceDate' => 'required|date',
            'quantity' => 'required|integer|min:1',
        ];

        $messages = [
            'selectedFolderId.required' => 'Veuillez sélectionner un dossier de voyage.',
            'serviceDate.required' => 'La date de la prestation est obligatoire.',
        ];

        // 2. 💡 RÉINTÉGRATION : Validation dynamique des champs personnalisés requis par le fournisseur
        if (!empty($this->product->custom_field_definitions)) {
            foreach ($this->product->custom_field_definitions as $def) {
                $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                if (($def['is_required'] ?? false) && $def['type'] !== 'toggle') {
                    $rules["customValues.{$key}"] = 'required';
                    $messages["customValues.{$key}.required"] = "Le champ '{$def['name']}' est requis par le prestataire.";
                }
            }
        }

        $this->validate($rules, $messages);

        // 3. Formatage des options sélectionnées pour correspondre à ton schéma de données
        $formattedOptions = [];
        foreach ($this->selectedOptions as $optionId => $data) {
            if ($data['enabled']) {
                $formattedOptions[] = [
                    'product_option_id' => $optionId,
                    'quantity' => $data['quantity']
                ];
            }
        }

        // 4. Création complète de la prestation dans le dossier
        $folderItem = FolderItem::create([
            'folder_id' => $this->selectedFolderId,
            'product_id' => $this->product->id,
            'service_date' => $this->serviceDate,
            'quantity' => $this->quantity,
            'selected_options' => $formattedOptions, // Enregistré en BDD
            'custom_values' => $this->customValues,       // Enregistré en BDD
            'item_status_id' => 1,                        // En attente
            'unit_price' => 0,                            // Calculé dynamiquement au prochain step
            'total_price' => 0,
        ]);

        // 5. Recalcul automatique des grilles tarifaires et saisons
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

        // Reset partiel
        $this->reset(['serviceDate', 'quantity', 'selectedFolderId']);
        $this->mount($this->product->id);
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/products/{record}';
    }
}