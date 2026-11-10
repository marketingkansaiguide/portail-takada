<?php

namespace App\Filament\Resources\Folders;

use App\Filament\Resources\Folders\Pages;
use App\Filament\Resources\Folders\FolderResource\RelationManagers; 
use App\Models\Folder;
use App\Models\FolderItem;
use App\Models\FolderMessage;
use App\Services\FolderDriveStorage;
use App\Models\Hotel;
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
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn; 
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;

class FolderResource extends Resource
{
    protected static ?string $model = Folder::class;
    protected static ?string $recordTitleAttribute = 'reference';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth('web')->user());
    }

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

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'folder_name', 'lead_traveler_name', 'hotel_booking_name', 'agency.name', 'mainSeller.name', 'client_contact_name', 'clientAgency.name'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return trim("{$record->reference} — " . ($record->folder_name ?: $record->lead_traveler_name ?: __('Dossier sans nom')));
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            __('Agence') => $record->agency?->name ?? ($record->clientAgency?->name ? $record->clientAgency->name . ' ' . __('(info)') : __('Non renseignée')),
            __('Voyageur principal') => $record->lead_traveler_name ?? __('Non renseigné'),
            __('Séjour') => collect([$record->start_date?->format('d/m/Y'), $record->end_date?->format('d/m/Y')])->filter()->implode(' → '),
        ];
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
        $internalItems = $get('internalItems') ?? [];

        $totalSale = 0;
        
        foreach ($items as $item) {
            $totalSale += (float) ($item['total_price'] ?? 0);
        }

        foreach ($internalItems as $item) {
            $totalSale += (float) ($item['total_price'] ?? 0);
        }
        
        $fee = (float) ($get('folder_fee') ?? 0);
        
        $set('total_price', $totalSale + $fee);
    }

    /**
     * Retourne les informations du sélecteur d'âge conservées dans custom_values.
     * La clé préfixée par un underscore est réservée aux métadonnées internes du dossier.
     */
    public static function getAgeSelectorData($customValues, int $fallbackQuantity = 1): array
    {
        $customValues = is_array($customValues) ? $customValues : [];
        $data = is_array($customValues['_age_selector'] ?? null)
            ? $customValues['_age_selector']
            : [];

        $adults = max(0, (int) ($data['adults'] ?? 0));
        $children = max(0, (int) ($data['children'] ?? 0));
        $teenagers = max(0, (int) ($data['teenagers'] ?? 0));

        if (($adults + $children + $teenagers) === 0) {
            $adults = max(1, $fallbackQuantity);
        }

        return [
            'adults' => $adults,
            'children' => $children,
            'teenagers' => $teenagers,
            'total' => $adults + $children + $teenagers,
            'child_age_limit' => isset($data['child_age_limit']) ? (int) $data['child_age_limit'] : null,
            'teen_age_limit' => isset($data['teen_age_limit']) ? (int) $data['teen_age_limit'] : null,
        ];
    }

    public static function updateItemPrices($set, $get)
    {
        $productId = $get('product_id');
        $serviceDate = $get('service_date');
        $itemQuantity = (int) ($get('quantity') ?? 1);
        $selectedOptions = $get('selected_options') ?? [];
        $customValues = $get('custom_values') ?? [];

        $product = $productId ? \App\Models\Product::with(['productPeriods.productPrices', 'productOptions.seasonalPrices'])->find($productId) : null;
        $product?->applyTariffVariant(array_column(is_array($selectedOptions) ? $selectedOptions : [], 'product_option_id'));
        $ageBaseTotal = null;
        $ageBaseBreakdown = null;
        $ageSelectorData = null;

        if ($product?->usesAgeSelector()) {
            $ageData = self::getAgeSelectorData($customValues, $itemQuantity);
            $ageData['adults'] = max(0, (int) ($get('age_selector_adults') ?? $ageData['adults']));
            $ageData['children'] = $product->hasChildSelector()
                ? max(0, (int) ($get('age_selector_children') ?? $ageData['children']))
                : 0;
            $ageData['teenagers'] = $product->hasTeenSelector()
                ? max(0, (int) ($get('age_selector_teenagers') ?? $ageData['teenagers']))
                : 0;

            if (($ageData['adults'] + $ageData['children'] + $ageData['teenagers']) === 0) {
                $ageData['adults'] = 1;
            }

            $ageData['total'] = $ageData['adults'] + $ageData['children'] + $ageData['teenagers'];
            $ageData['child_age_limit'] = (int) ($product->child_age_limit ?? 11);
            $ageData['teen_age_limit'] = $product->teen_age_limit ? (int) $product->teen_age_limit : null;
            $customValues['_age_selector'] = $ageData;

            $set('quantity', $ageData['total']);
            $set('pax_adults', $ageData['adults']);
            $set('pax_children', $ageData['children']);
            $set('pax_teenagers', $ageData['teenagers']);
            $set('custom_values', $customValues);
            $itemQuantity = $ageData['total'];
            $ageSelectorData = $ageData;
        }
        
        $basePrice = 0;
        $basePriceRow = null;
        $basePricePeriod = null;
        if ($product && $serviceDate) {
            if ($period = $product->periodForDate($serviceDate)) {
                $basePricePeriod = $period;
                $validPrices = $period->productPrices->where('min_pax', '<=', $itemQuantity)->where('max_pax', '>=', $itemQuantity)
                    ->reject(fn ($price) => $price->isExtraPaxRate());
                if ($validPrices->isNotEmpty()) {
                    $basePriceRow = $validPrices->first();
                    $basePrice = $basePriceRow->price;
                    if ($ageSelectorData && $product->usesAgeSelector()) {
                        $ageBaseBreakdown = $product->calculateAgeBasedBreakdown($period, $itemQuantity, $ageSelectorData['adults'], $ageSelectorData['children'], $ageSelectorData['teenagers']);
                        $ageBaseTotal = $ageBaseBreakdown['total'] ?? null;
                    }
                } else {
                    $basePriceRow = $period->productPrices->reject(fn ($price) => $price->isExtraPaxRate())->sortByDesc('max_pax')->first();
                    $basePrice = $basePriceRow?->price ?? 0;
                    if ($ageSelectorData && $product->usesAgeSelector()) {
                        $ageBaseBreakdown = $product->calculateAgeBasedBreakdown($period, $itemQuantity, $ageSelectorData['adults'], $ageSelectorData['children'], $ageSelectorData['teenagers']);
                        $ageBaseTotal = $ageBaseBreakdown['total'] ?? null;
                    }
                }
            }
        }

        $perPaxOptionsTotal = 0;
        $fixedOptionsTotal = 0;
        $guidePurchaseTotal = 0;
        $hasGuidePurchasePrice = false;

        if (is_array($selectedOptions)) {
            foreach ($selectedOptions as $optData) {
                if (empty($optData['product_option_id'])) continue;
                $option = \App\Models\ProductOption::find($optData['product_option_id']);
                if ($option) {
                    $guidePurchaseTotal += (float) ($option->purchasePriceForDate($serviceDate, $itemQuantity) ?? 0);
                    $pricingQuantity = $ageSelectorData ? $ageSelectorData['adults'] : $itemQuantity;
                    $mod = $option->priceModifierForDate($serviceDate, 'adult', $pricingQuantity);
                    if ($option->billing_type === 'per_pax' || $option->hasExtraPaxSeasonalModifierForDate($serviceDate, $pricingQuantity)) {
                        if ($ageSelectorData) {
                            $billableGroups = $option->billableAgeGroupQuantitiesForDate($serviceDate, $itemQuantity, $ageSelectorData['adults'], $ageSelectorData['children'], $ageSelectorData['teenagers']);
                            $adultMod = $option->priceModifierForDate($serviceDate, 'adult', $ageSelectorData['adults']);
                            $childMod = $option->priceModifierForDate($serviceDate, 'child', $ageSelectorData['children']);
                            $teenMod = $option->priceModifierForDate($serviceDate, 'teen', $ageSelectorData['teenagers']);
                            $perPaxOptionsTotal += (($adultMod * $billableGroups['adults']) + ($childMod * $billableGroups['children']) + ($teenMod * $billableGroups['teenagers'])) / max(1, $itemQuantity);
                        } else {
                            $perPaxOptionsTotal += ($mod * $option->billableQuantityForDate($serviceDate, $itemQuantity)) / max(1, $itemQuantity);
                        }
                    } elseif ($option->billing_type === 'per_booking') {
                        $fixedOptionsTotal += $mod;
                    } elseif ($option->billing_type === 'manual') {
                        $optQty = (float) ($optData['quantity'] ?? 1);
                        $fixedOptionsTotal += ($mod * $optQty);
                    }
                }
            }
        }

        $guideBasePurchase = $product?->guideBasePurchaseForDate($serviceDate, (int) $itemQuantity);
        if ($guideBasePurchase !== null) {
            $guidePurchaseTotal += $guideBasePurchase;
            $hasGuidePurchasePrice = true;
        }

        $multiplier = $product ? $product->multiplierFrom(is_array($customValues) ? $customValues : null) : 1;
        if ($product && (int) $product->category_id === 3 && $basePriceRow && $basePriceRow->purchase_price !== null) {
            $guidePurchaseTotal += (float) $basePriceRow->purchase_price * max(1, $itemQuantity) * $multiplier;
            $hasGuidePurchasePrice = true;
        }

        // --- NOUVELLE LOGIQUE DE CALCUL ---
        $baseTotal = $ageBaseTotal !== null
            ? (float) $ageBaseTotal
            : $product?->baseRateTotal($basePriceRow, $itemQuantity, $basePricePeriod) ?? ((float) $basePrice * (float) $itemQuantity);
        $baseTotal *= $multiplier;
        $baseUnitPrice = $ageBaseTotal !== null
            ? (float) $ageBaseTotal / max(1, $itemQuantity)
            : $baseTotal / max(1, $itemQuantity);
        $unitPrice = $baseUnitPrice
            + (float) $perPaxOptionsTotal;
        $fraisFixesBase = $baseTotal + ((float) $perPaxOptionsTotal * (float) $itemQuantity) + (float) $fixedOptionsTotal;

        if ($product && $product->product_type === 'transport') {
            $routePurchaseTotal = 0;

            if (is_array($customValues) && isset($customValues['transport_routes']) && is_array($customValues['transport_routes'])) {
                foreach ($customValues['transport_routes'] as $route) {
                    // On additionne le prix d'achat réel renseigné par l'agent
                    $routePurchaseTotal += (float) ($route['purchase_price'] ?? 0);
                }
            }

            // Pour les transports, l'affichage unitaire représente nos frais
            $set('unit_price', $unitPrice); 

            // PRIX VENTE = Frais d'agence (fraisFixesBase) + Prix Coûtant des billets (routePurchaseTotal)
            $set('total_price', $fraisFixesBase + $routePurchaseTotal);
            
            // PRIX ACHAT = Seulement le coût des billets
            $set('purchase_total_price', $routePurchaseTotal);

        } else {
            // Prestation normale (Hotel, Guide, Activité...)
            $set('unit_price', $unitPrice);
            $set('total_price', $fraisFixesBase);
            if (in_array((int) ($product?->category_id ?? 0), [3, 4], true) && $hasGuidePurchasePrice) {
                $set('purchase_unit_price', $guidePurchaseTotal);
                $set('purchase_total_price', $guidePurchaseTotal);
            }
        }
    }

    /**
     * Regroupe les fichiers transmis par l'agence dans le chat et les champs
     * de prestation. Les documents ajoutés par l'équipe Takada restent dans
     * l'encart « Documents du dossier » distinct.
     */
    public static function renderAgencySubmittedDocuments(?Folder $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('');
        }

        $documents = [];
        $seen = [];
        

        $addDocument = function (string $path, string $source, ?Carbon $submittedAt = null) use (&$documents, &$seen): void {
            $path = ltrim(str_replace('\\', '/', trim($path)), '/');

            $isDriveFile = app(FolderDriveStorage::class)->isDrivePath($path);
            if ($path === '' || (! $isDriveFile && ! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) || isset($seen[$source . '|' . $path])) {
                return;
            }

            $seen[$source . '|' . $path] = true;
            $documents[] = [
                'path' => $path,
                'name' => basename($path),
                'source' => $source,
                'submitted_at' => $submittedAt,
            ];
        };

        FolderMessage::query()
            ->with('user')
            ->where('folder_id', $record->id)
            ->whereNotNull('attachment_path')
            ->oldest()
            ->get()
            ->filter(fn (FolderMessage $message): bool => ! ($message->user?->isStaff() ?? false))
            ->each(fn (FolderMessage $message) => $addDocument(
                $message->attachment_path,
                __('Messagerie agence'),
                $message->created_at,
            ));

        foreach ($record->folderItems()->with('product')->get() as $item) {
            $serviceFiles = [];
            $collectServiceFile = function (mixed $value) use (&$collectServiceFile, &$serviceFiles): void {
                if (is_array($value)) {
                    foreach ($value as $entry) {
                        $collectServiceFile($entry);
                    }

                    return;
                }

            if (is_string($value)) {
                if (app(FolderDriveStorage::class)->isDrivePath($value)) {
                    $serviceFiles[] = $value;
                }

                preg_match_all('/(folders\/custom_fields\/[^\r\n]+?\.(?:pdf|jpe?g|png|docx?|xlsx?|xls|csv))/i', $value, $matches);

                    foreach ($matches[1] ?? [] as $path) {
                        $serviceFiles[] = $path;
                    }
                }
            };

            $collectServiceFile($item->custom_values ?? []);
            $source = __('Prestation : :name', ['name' => $item->product?->name ?? $item->title ?? __('Prestation sans nom')]);

            foreach (array_unique($serviceFiles) as $path) {
                $addDocument($path, $source, $item->updated_at);
            }
        }

        if ($documents === []) {
            return new HtmlString('<span style="color: #6b7280; font-style: italic; font-size: 0.875rem;">' . e(__('Aucun fichier transmis par l’agence pour le moment.')) . '</span>');
        }

        usort($documents, fn (array $left, array $right) => ($right['submitted_at']?->getTimestamp() ?? 0) <=> ($left['submitted_at']?->getTimestamp() ?? 0));

        $html = '<div style="display: flex; flex-direction: column; gap: 0.65rem;">';
        foreach ($documents as $document) {
            $storage = app(FolderDriveStorage::class);
            $url = $storage->url($record, $document['path']);
            $name = $storage->fileName($document['path']);
            $date = $document['submitted_at']?->format('d/m/Y H:i');
            $meta = e($document['source']) . ($date ? ' · ' . e(__('Ajouté le :date', ['date' => $date])) : '');

            $html .= '<div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.75rem; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem;">'
                . '<div style="min-width: 0; display: flex; align-items: center; gap: 0.65rem;">'
                . '<svg style="width: 1.25rem; height: 1.25rem; color: var(--tk-accent); flex-shrink: 0;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>'
                . '<div style="min-width: 0;"><a href="' . e($url) . '" target="_blank" style="display: block; color: var(--tk-accent); font-size: 0.875rem; font-weight: 700; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . e($name) . '</a>'
                . '<span style="display: block; margin-top: 0.15rem; color: #64748b; font-size: 0.75rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . $meta . '</span></div></div>'
                . '<a href="' . e($url) . '" download title="' . e(__('Télécharger')) . '" style="flex-shrink: 0; color: #475569; padding: 0.25rem;"><svg style="width: 1.2rem; height: 1.2rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg></a></div>';
        }

        return new HtmlString($html . '</div>');
    }

    /**
     * Schéma d'une ligne de prestation, commun aux prestations commandées
     * (mode « ordered », identique au schéma historique) et aux prestations
     * préparées des dossiers internes (mode « planned » : sans statut fournisseur
     * ni suivi de facture). Même moteur tarifaire (updateItemPrices).
     *
     * @return array<int, mixed>
     */
    public static function folderItemSchema(string $mode = 'ordered'): array
    {
        return [
                    Hidden::make('pax_adults')->dehydrated(true),
                    Hidden::make('pax_children')->dehydrated(true),

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
                            // Espace commercial : le fournisseur est déduit du produit, jamais demandé au commercial.
                            ->extraFieldWrapperAttributes($mode === 'commercial' ? ['style' => 'display:none'] : [])
                            ->hidden(fn (\Filament\Schemas\Components\Utilities\Get $get) =>
                                    \App\Models\Product::find($get('product_id'))?->product_type === 'transport'
                                )
                            ->hint(function ($get) {
                                try {
                                    $productId = $get('product_id');
                                    if ($productId && !$get('supplier_id')) {
                                        $count = \App\Models\ProductSupplier::where('product_id', $productId)->count();
                                        if ($count > 1) {
        return new \Illuminate\Support\HtmlString('<span style="color:red; font-weight:bold;">⚠️ ' . e(__('Sélection requise pour générer les documents')) . '</span>');
                                        }
                                    }
                                } catch (\Exception $e) {}
                                return null;
                            })
                            ->columnSpan(4),

                        Select::make('product_id')
                            ->relationship('product', 'name', fn ($query, Get $get) => $query->where(
                                fn ($q) => $q->where('products.is_public', true)->orWhere('products.id', $get('product_id'))
                            ))
                            ->label(__('Produit / Activité'))
                            ->required()
                            ->searchable()
                            ->preload($mode !== 'commercial')
                            // Espace commercial : recherche côté serveur (2 caractères minimum, 15 résultats),
                            // prestations fréquentes à champ vide ; le catalogue n'est jamais préchargé.
                            ->when($mode === 'commercial', fn (Select $select) => $select
                                ->allowHtml()
                                ->searchDebounce(250)
                                ->noSearchResultsMessage(__('Aucune prestation trouvée. Essayez un autre mot-clé.'))
                                ->searchPrompt(__('Tapez au moins 2 caractères : nom, ville, catégorie…'))
                                ->options(fn () => \App\Support\CatalogueSearch::frequent(auth('web')->id()))
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => \App\Support\CatalogueSearch::search($search, \App\Support\CatalogueSearch::LIMIT, (int) $get('product_id') ?: null))
                                ->getOptionLabelUsing(fn ($value): ?string => ($name = \App\Models\Product::whereKey($value)->value('name')) ? e($name) : null))
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
                            ->hidden(in_array($mode, ['planned', 'commercial'], true)) // statut fournisseur : seulement après commande
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

                    Group::make()->hidden(in_array($mode, ['planned', 'commercial'], true))->schema([
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
                            ->native(false)->displayFormat($mode === 'commercial' ? 'd/m/Y' : null)
                            ->required()
                            ->live()
                            ->minDate(fn ($get) => $get('../../start_date') ? Carbon::parse($get('../../start_date'))->startOfDay() : null)
                            ->maxDate(fn ($get) => $get('../../end_date') ? Carbon::parse($get('../../end_date'))->endOfDay() : null)
                             ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get))
                             ->columnSpan(1),

                         TextInput::make('age_selector_adults')
                             ->label(__('Adultes'))
                             ->numeric()
                             ->default(1)
                             ->minValue(0)
                             ->live()
                             ->dehydrated(false)
                             ->afterStateHydrated(function ($component, $state, Get $get): void {
                                 $stored = $get('pax_adults') ?? data_get($get('custom_values'), '_age_selector.adults');
                                 if ($stored !== null) {
                                     $component->state((int) $stored);
                                 }
                             })
                             ->visible(fn (Get $get): bool => (bool) \App\Models\Product::find($get('product_id'))?->usesAgeSelector())
                             ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get))
                             ->columnSpan(1),

                         TextInput::make('age_selector_children')
                             ->label(__('Enfants'))
                             ->numeric()
                             ->default(0)
                             ->minValue(0)
                             ->live()
                             ->dehydrated(false)
                             ->afterStateHydrated(function ($component, $state, Get $get): void {
                                 $stored = $get('pax_children') ?? data_get($get('custom_values'), '_age_selector.children');
                                 if ($stored !== null) {
                                     $component->state((int) $stored);
                                 }
                             })
                             ->visible(fn (Get $get): bool => (bool) \App\Models\Product::find($get('product_id'))?->hasChildSelector())
                             ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get))
                             ->columnSpan(1),

                         TextInput::make('age_selector_teenagers')
                             ->label(__('Adolescents'))
                             ->numeric()
                             ->default(0)
                             ->minValue(0)
                             ->live()
                             ->dehydrated(false)
                             ->afterStateHydrated(function ($component, $state, Get $get): void {
                                 $stored = $get('pax_teenagers') ?? data_get($get('custom_values'), '_age_selector.teenagers');
                                 if ($stored !== null) {
                                     $component->state((int) $stored);
                                 }
                             })
                             ->visible(fn (Get $get): bool => (bool) \App\Models\Product::find($get('product_id'))?->hasTeenSelector())
                             ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get))
                             ->columnSpan(1),

                         Placeholder::make('age_selector_hint')
                             ->hiddenLabel()
                             ->content(function (Get $get) {
                                 $product = \App\Models\Product::find($get('product_id'));
                                 if (!$product?->usesAgeSelector()) {
                                     return null;
                                 }

                                 $childLimit = (int) ($product->child_age_limit ?? 11);
                                 $childHint = $product->hasChildSelector()
                                     ? __('Enfant = jusqu\'à :') . ' <strong>' . $childLimit . ' ' . __('ans inclus') . '</strong>'
                                     : '';
                                 $teenHint = $product->hasTeenSelector()
                                     ? ($childHint !== '' ? ' · ' : '') . __('Adolescent = de :min à :max ans', ['min' => $childLimit + 1, 'max' => (int) $product->teen_age_limit])
                                     : '';

                                 return new \Illuminate\Support\HtmlString(
                                     '<span style="font-size:0.8rem;color:#64748b;">' .
                                     $childHint . e($teenHint) . '</span>'
                                 );
                             })
                             ->visible(fn (Get $get): bool => (bool) \App\Models\Product::find($get('product_id'))?->usesAgeSelector())
                             ->columnSpan(2),

                         TextInput::make('quantity')
                             ->label(__('Total Pax'))
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
                             ->required()
                             ->live()
                             ->readOnly(fn (Get $get): bool => (bool) \App\Models\Product::find($get('product_id'))?->usesAgeSelector())
                             ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get))
                             ->columnSpan(1),

                        TextInput::make('purchase_total_price')
                            ->label(__('Total Achat (¥)'))
                            ->numeric()
                            ->default($mode === 'commercial' ? null : 0)
                            ->live()
                            ->extraFieldWrapperAttributes($mode === 'commercial' ? ['style' => 'display:none'] : [])
                            ->columnSpan(2),

                        \Filament\Forms\Components\Placeholder::make('price_line')
                            ->hiddenLabel()
                            ->visible($mode === 'commercial')
                            ->content(function (Get $get) {
                                $total = (float) ($get('total_price') ?? 0);
                                $qty = max(1, (int) ($get('quantity') ?? 1));
                                if ($total <= 0) {
                                    return __('Prix : non calculé (date et produit requis).');
                                }

                                return __('Prix :') . ' ' . number_format($total, 0, ',', ' ') . ' ¥ (' . number_format($total / $qty, 0, ',', ' ') . ' ¥ × ' . $qty . ')';
                            })
                            ->columnSpan(6),

                        TextInput::make('unit_price')
                            ->label(__('Vente Unit. (¥)'))
                            ->extraFieldWrapperAttributes($mode === 'commercial' ? ['style' => 'display:none'] : [])
                            ->numeric()
                            ->default(0) 
                            ->readOnly() 
                            ->dehydrated()
                            ->required(false)
                            ->columnSpan(1),

                        TextInput::make('total_price')
                            ->label(__('Total Vente (¥)'))
                            ->extraFieldWrapperAttributes($mode === 'commercial' ? ['style' => 'display:none'] : [])
                            ->numeric()
                            ->default(0) 
                            ->readOnly() 
                            ->dehydrated()
                            ->required(false)
                            ->columnSpan(1),
                    ])->columns(6),

                    Repeater::make('selected_options')
                        ->label(__('Options Sélectionnées'))
                        ->reorderable($mode !== 'commercial')
                        ->compact($mode === 'commercial')
                        ->addActionLabel(__('Ajouter une option tarifaire'))
                        ->defaultItems(0)
                        ->live()
                        ->afterStateUpdated(function ($set, $get) {
                            self::updateItemPrices($set, $get);
                        })
                        ->schema([
                            Select::make('product_option_id')
                                ->label(function ($get) {
                                    // Le libellé porte la déclinaison (groupe) de l'option choisie
                                    $optionId = $get('product_option_id');
                                    $group = $optionId ? \App\Models\ProductOption::find($optionId)?->group_name : null;
                                    return filled($group) ? $group : __('Option proposée');
                                })
                                ->options(function ($get) {
                                    $productId = $get('../../product_id');
                                    if (!$productId) return [];
                                    // Options regroupées par déclinaison (group_name) pour ne plus les mélanger
                                    $options = \App\Models\ProductOption::where('product_id', $productId)
                                        ->orderBy('sort_order')->orderBy('id')->get();
                                    // Chaque ligne ne propose que les choix de SA déclinaison ;
                                    // une nouvelle ligne ne propose que les déclinaisons pas encore utilisées.
                                    $currentId = $get('product_option_id');
                                    $currentGroup = $currentId ? $options->firstWhere('id', (int) $currentId)?->group_name : null;
                                    if (filled($currentGroup)) {
                                        $options = $options->where('group_name', $currentGroup)->values();
                                    } else {
                                        $usedIds = collect($get('../../selected_options') ?? [])->pluck('product_option_id')->filter()->map(fn ($i) => (int) $i);
                                        $usedGroups = $options->whereIn('id', $usedIds)->pluck('group_name')->filter()->unique();
                                        $options = $options->reject(fn ($o) => filled($o->group_name) && $usedGroups->contains($o->group_name))->values();
                                    }
                                    if ($options->every(fn ($o) => blank($o->group_name))) {
                                        return $options->pluck('name', 'id')->toArray();
                                    }
                                    return $options
                                        ->groupBy(fn ($o) => filled($o->group_name) ? $o->group_name : __('Autres options'))
                                        ->map(fn ($g) => $g->pluck('name', 'id')->toArray())
                                        ->toArray();
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
                                    $parentSet = function($k, $v) use ($set) { $set('../../'.$k, $v); };
                                    $parentGet = function($k) use ($get, $state) { 
                                        if ($k === 'selected_options') {
                                            return $get('../../selected_options') ?? [];
                                        }
                                        return $get('../../'.$k); 
                                    };
                                    self::updateItemPrices($parentSet, $parentGet);
                                }),
                        ])
                        ->columns(2),

                    Group::make()
                        ->statePath('custom_values')
                        ->schema(function (Get $get) {
                            $productId = $get('product_id');
                            if (!$productId) return [];

                            $product = \App\Models\Product::find($productId);
                            if (!$product) return [];

                            // Conserve les compteurs adultes/enfants
                            // transmis par le front office lors de la
                            // sauvegarde du dossier.
                            $ageSelectorMetadata = Hidden::make('_age_selector')
                                ->dehydrated(true);

                            // 💡 CAS 1 : PRODUIT DE TRANSPORT (BILLETTERIE TRAIN / BUS - MULTI-TRAJETS)
                            if ($product->product_type === 'transport') {
                                return [
                                    $ageSelectorMetadata,
                                    Section::make(__('Trajets'))
                                        ->description(__('Un trajet par billet.'))
                                        ->compact()
                                        ->schema([
Repeater::make('transport_routes')
    ->hiddenLabel()
    ->addActionLabel(__('Ajouter un trajet'))
    // Libellé lisible même replié : « Trajet 2 · Kyoto → Tokyo · 07/08 · 2 pax »
    ->itemLabel(function (array $state, ?int $index = null): string {
        $parts = [__('Trajet') . ($index !== null ? ' ' . ($index + 1) : '')];
        if (!empty($state['departure_station']) || !empty($state['arrival_station'])) {
$parts[] = ($state['departure_station'] ?: '…') . ' → ' . ($state['arrival_station'] ?: '…');
        }
        if (!empty($state['departure_date'])) {
try { $parts[] = \Illuminate\Support\Carbon::parse($state['departure_date'])->format('d/m'); } catch (\Throwable $e) {}
        }
        if (!empty($state['pax_count'])) {
$parts[] = (int) $state['pax_count'] . ' pax';
        }
        return implode(' · ', $parts);
    })
    ->collapsible()
    ->live()
    ->afterStateUpdated(function ($set, $get) {
        $parentSet = function($k, $v) use ($set) { $set('../'.$k, $v); };
        $parentGet = function($k) use ($get) { return $get('../'.$k); };
        self::updateItemPrices($parentSet, $parentGet);
    })
    ->schema([
        Group::make()->schema([
Select::make('departure_station')
    ->label(__('Gare de départ'))
    ->searchable()
    ->required()
    ->placeholder(__('Rechercher gare ou station...'))
    ->getSearchResultsUsing(function (string $search): array {
        $trains = \App\Models\TrainStation::where(function($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                ->orWhere('name_ja', 'like', "%{$search}%")
                ->orWhere('prefecture', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%");
            })
            ->orderBy('importance_score', 'desc')
            ->limit(15)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->name_en,
                'label' => "🚆 {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : ""),
                'score' => $s->importance_score ?? 10
            ]);

        $buses = \App\Models\BusStation::where('name_en', 'like', "%{$search}%")
            ->orWhere('name_ja', 'like', "%{$search}%")
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->name_en,
                'label' => "🚌 [Bus] {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : ""),
                'score' => 90
            ]);

        return $trains->concat($buses)->sortByDesc('score')->take(15)->pluck('label', 'id')->toArray();
    })
    ->getOptionLabelUsing(fn ($value) => $value),

Select::make('arrival_station')
    ->label(__('Gare d\'arrivée'))
    ->searchable()
    ->required()
    ->placeholder(__('Rechercher gare ou station...'))
    ->getSearchResultsUsing(function (string $search): array {
        $trains = \App\Models\TrainStation::where(function($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                ->orWhere('name_ja', 'like', "%{$search}%")
                ->orWhere('prefecture', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%");
            })
            ->orderBy('importance_score', 'desc')
            ->limit(15)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->name_en,
                'label' => "🚆 {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : ""),
                'score' => $s->importance_score ?? 10
            ]);

        $buses = \App\Models\BusStation::where('name_en', 'like', "%{$search}%")
            ->orWhere('name_ja', 'like', "%{$search}%")
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->name_en,
                'label' => "🚌 [Bus] {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : ""),
                'score' => 90
            ]);

        return $trains->concat($buses)->sortByDesc('score')->take(15)->pluck('label', 'id')->toArray();
    })
    ->getOptionLabelUsing(fn ($value) => $value),
        ])->columns(2),

        Group::make()->schema([
DatePicker::make('departure_date')
    ->label(__('Date'))
    ->native(false)
    ->displayFormat('d/m/Y')
    ->required(),

TextInput::make('departure_time')
    ->label(__('Départ'))
    ->placeholder(__('09:32')),

TextInput::make('arrival_time')
    ->label(__('Arrivée'))
    ->placeholder(__('12:05')),

TextInput::make('train_number')
    ->label(__('N° train / bus'))
    ->placeholder(__('Hikari 502, voiture 3')),
        ])->columns(['default' => 2, 'md' => 4]),

        // Classe, passagers et achat sur une seule ligne
        Group::make()->schema([
Select::make('option_id')
    ->label(__('Classe'))
    ->options(fn () => \App\Models\ProductOption::where('product_id', $productId)->pluck('name', 'id'))
    ->searchable()
    ->required(),

TextInput::make('pax_count')
    ->label(__('Passagers'))
    ->numeric()
    ->minValue(1)
    ->suffix(__('pax'))
    ->default(1)
    ->required(),

Select::make('supplier_id')
    ->label(__('Fournisseur'))
    ->hintColor('gray')
    ->hintIcon('heroicon-m-information-circle', tooltip: __('Qui émet ce billet (JR, Willer Express...)'))
    ->options(fn () => \App\Models\Supplier::pluck('name', 'id'))
    ->searchable()
    ->preload()
    ->nullable(),

TextInput::make('purchase_price')
    ->label(__('Prix d\'achat'))
    ->hintColor('gray')
    ->hintIcon('heroicon-m-information-circle', tooltip: __('Total du trajet, refacturé à l\'agence au prix coûtant.'))
    ->suffix('¥')
    ->numeric()
    ->default(0)
    ->live()
    ->afterStateUpdated(function ($set, $get) {
        $parentSet = function($k, $v) use ($set) { $set('../../'.$k, $v); };
        $parentGet = function($k) use ($get) { return $get('../../'.$k); };
        self::updateItemPrices($parentSet, $parentGet);
    }),
        ])->columns(['default' => 2, 'md' => 4]),

        Textarea::make('specific_requests')
->label(__('Demande spécifique'))
->placeholder(__('Siège côté fenêtre, bagages volumineux, correspondance…'))
->rows(2)
->autosize()
->maxLength(5000)
->columnSpanFull(),
    ])
                                        ])
                                ];
                            }

                            // 💡 CAS 2 : PRODUIT CLASSIQUE (Champs personnalisés standards)
                            if (empty($product->custom_field_definitions)) {
                                return [$ageSelectorMetadata];
                            }

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

                                if ($type === 'file') {
                                    $fields[] = Hidden::make($key);
                                    
                                    $fields[] = Placeholder::make($key . '_display')
                                        ->label($label . ($perPax ? ' (' . __('Par passager') . ')' : ''))
                                        ->content(function ($get, ?\Illuminate\Database\Eloquent\Model $record) use ($key) {
                                            $val = $get($key);
                                            $folder = $record?->folder;
                                            
                                            if (empty($val)) {
                                                return new HtmlString('<span style="color:#9ca3af; font-style:italic;">Aucun fichier fourni</span>');
                                            }
                                            
                                            $html = preg_replace_callback('/(folders\/custom_fields\/.*?\.(?:pdf|jpg|jpeg|png|doc|docx))/i', function ($m) use ($folder) {
                                                $url = $folder
                                                    ? route('folders.legacy-file', ['folder' => $folder, 'path' => trim($m[1])])
                                                    : '#';
                                                return "<div style='margin-top: 6px; margin-bottom: 6px;'><a href=\"{$url}\" style=\"display: inline-block; background-color: #ecfdf5; color: #059669; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 0.85rem; border: 1px solid #a7f3d0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);\">📄 Télécharger / Voir le document</a></div>";
                                            }, (string)$val);
                                            
                                            return new HtmlString(nl2br($html));
                                        });

                                } else {
                                    if ($perPax) {
                                        $label .= ' (' . __('Par passager') . ')';
                                        $field = match ($type) {
                                            'textarea' => Textarea::make($key)->label($label)->placeholder($placeholder)->rows(3),
                                            default => Textarea::make($key)->label($label)->placeholder($placeholder)->rows(2),
                                        };
                                    } else {
                                        $field = match ($type) {
                                            'textarea' => Textarea::make($key)->label($label)->placeholder($placeholder)->rows(2),
                                            'number' => TextInput::make($key)->numeric()->label($label)->placeholder($placeholder),
                                            'date' => DatePicker::make($key)->native(false)->label($label),
                                            'toggle' => Toggle::make($key)->label($label)->inline(false),
                                            'select' => Select::make($key)->label($label)->options(array_combine($def['choices'] ?? [], $def['choices'] ?? [])),
                                            
                                            // 💡 Autocomplétion Gare de Train
                                            'train_station' => Select::make($key)
                                                ->label($label)
                                                ->searchable()
                                                ->placeholder($placeholder ?? __('Rechercher une gare...'))
                                                ->getSearchResultsUsing(function (string $search): array {
                                                    return \App\Models\TrainStation::where(function($q) use ($search) {
                                                            $q->where('name_en', 'like', "%{$search}%")
                                                            ->orWhere('name_ja', 'like', "%{$search}%")
                                                            ->orWhere('name_kana', 'like', "%{$search}%")
                                                            ->orWhere('prefecture', 'like', "%{$search}%")
                                                            ->orWhere('city', 'like', "%{$search}%")
                                                            ->orWhere('aliases', 'like', "%{$search}%");
                                                        })
                                                        ->orderBy('importance_score', 'desc')
                                                        ->orderBy('name_en', 'asc')
                                                        ->limit(20)
                                                        ->get()
                                                        ->mapWithKeys(function ($station) {
                                                            $location = $station->city ? $station->city : $station->prefecture;
                                                            $locationSuffix = $location ? " - {$location}" : "";
                                                            return [$station->name_en => "🚆 {$station->name_en}" . ($station->name_ja ? " ({$station->name_ja})" : "") . $locationSuffix];
                                                        })
                                                        ->toArray();
                                                })
                                                ->getOptionLabelUsing(function ($value): ?string {
                                                    $station = \App\Models\TrainStation::where('name_en', $value)->first();
                                                    return $station ? "{$station->name_en}" . ($station->name_ja ? " ({$station->name_ja})" : "") : $value;
                                                }),

                                            // 💡 Autocomplétion Station de Bus
                                            'bus_station' => Select::make($key)
                                                ->label($label)
                                                ->searchable()
                                                ->placeholder($placeholder ?? __('Rechercher une station...'))
                                                ->getSearchResultsUsing(function (string $search): array {
                                                    return \App\Models\BusStation::where('name_en', 'like', "%{$search}%")
                                                        ->orWhere('name_ja', 'like', "%{$search}%")
                                                        ->orWhere('address', 'like', "%{$search}%")
                                                        ->limit(20)
                                                        ->get()
                                                        ->mapWithKeys(fn ($station) => [$station->name_en => "🚌 {$station->name_en}" . ($station->name_ja ? " ({$station->name_ja})" : "")])
                                                        ->toArray();
                                                })
                                                ->getOptionLabelUsing(function ($value): ?string {
                                                    $station = \App\Models\BusStation::where('name_en', $value)->first();
                                                    return $station ? "{$station->name_en}" . ($station->name_ja ? " ({$station->name_ja})" : "") : $value;
                                                }),

                                            'client_hotel' => TextInput::make($key)
                                                ->label($label)
                                                ->placeholder($placeholder ?? __('Rechercher un hébergement ou saisir son nom...'))
                                                ->datalist(fn (): array => Hotel::query()->orderBy('name')->pluck('name')->all())
                                                ->helperText(__('Choisissez un hébergement de la base ou saisissez-en un autre.')),

                                            default => TextInput::make($key)->label($label)->placeholder($placeholder),
                                        };
                                    }

                                    if ($isRequired && (!isset($type) || $type !== 'toggle')) {
                                        $field->rules(['required']);
                                    }

                                    $fields[] = $field;
                                }
                            }

                            return [
                                $ageSelectorMetadata,
                                Section::make(__('Informations spécifiques (Réponses)'))
                                    ->description(__('Remplissez les critères requis pour cette prestation. Vous pouvez ignorer les suggestions et taper un texte libre.'))
                                    ->schema($fields)
                                    ->columns(2)
                            ];
                        }),

                    // Consignes saisies par le commercial (prestations issues d'une préparation).
                    // Les modifications après commande sont tracées dans l'historique du dossier.
                    Section::make(__('Consignes du commercial'))
                        ->description(__('Informations de la fiche commerciale. Jamais transmises à un fournisseur, un guide ou une agence.'))
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $mode === 'ordered' && filled($get('planned_item_id')))
                        ->columns(2)
                        ->schema([
                            \Filament\Forms\Components\TextInput::make('meeting_point')->label(__('Lieu de rendez-vous'))->maxLength(255),
                            \Filament\Forms\Components\TextInput::make('meeting_time')->label(__('Heure de rendez-vous'))->maxLength(10),
                            Textarea::make('ticketing_notes')->label(__('Informations pour la billetterie'))->rows(3)->columnSpanFull(),
                        ]),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                FolderStickyHeader::component(),


                // -------------------------------------------------------------
                // 1. COLONNE DE GAUCHE (Span 2)
                // -------------------------------------------------------------
                Group::make()->schema([
                    InternalFolderFields::section(),

                    Section::make(__('Informations Générales'))->extraAttributes(['data-tk-anchor' => 'informations'])
                        ->columns(4)
                        ->schema([
                            TextInput::make('folder_name')
                                ->label(__('Nom du dossier'))
                ->placeholder(__('Ex: Circuit Hanami 2026'))
                                ->required(),

                            TextInput::make('lead_traveler_name')
                                ->label(__('Pax Leader'))
                ->placeholder(__('Ex: Jean Dupont'))
                                ->required(fn (?Folder $record): bool => ! $record?->isInternal()),

                            TextInput::make('hotel_booking_name')
                                ->label(__('Nom réservation hôtel'))
                                ->placeholder(__('Si différent')),

                            Select::make('segment')
                                ->visible(fn (?Folder $record): bool => ! $record?->isInternal())
                                ->label(__('Segment'))
                                ->options(function () {
                                    $user = auth('web')->user();
                                    $allowed = $user?->visibleFolderSegments();

                                    return $allowed === null
                                        ? Folder::SEGMENTS
                                        : array_intersect_key(Folder::SEGMENTS, array_flip($allowed));
                                })
                                ->placeholder(__('Non classé'))
                                ->helperText(__('Défini automatiquement selon votre profil si unique.'))
                                ->default(fn () => auth('web')->user()?->defaultFolderSegment()),

                            // Dossier interne : jamais d'agence propriétaire ni de vendeur agence (lot 2).
                            Select::make('agency_id')
                                ->visible(fn (?Folder $record): bool => ! $record?->isInternal())
                                ->relationship('agency', 'name')
                                ->label(__('Agence émettrice'))
                                ->required()
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn ($set) => $set('main_seller_id', null)),

                            Select::make('main_seller_id')
                                ->visible(fn (?Folder $record): bool => ! $record?->isInternal())
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
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    if ($state !== 'guide') {
                                        $set('ticket_dispatch_guide_id', null);
                                        $set('ticket_dispatch_guide_name', null);
                                    }
                                })
                                ->required(),

                            Select::make('ticket_dispatch_guide_id')
                                ->label(__('Rechercher un guide'))
                                ->searchable()
                                ->searchDebounce(500)
                                ->getSearchResultsUsing(fn (string $search): array => app(\App\Services\GuidePlanningApi::class)->search($search))
                                ->getOptionLabelUsing(function ($value): ?string {
                                    $guide = app(\App\Services\GuidePlanningApi::class)->find($value);

                                    return $guide ? $guide['name'] . ($guide['phone'] ? " — {$guide['phone']}" : '') : null;
                                })
                                ->helperText(__('Le nom et les coordonnées connues du guide sont récupérés depuis le planning externe.'))
                                ->visible(fn (Get $get): bool => $get('ticket_dispatch_method') === 'guide')
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                    $guide = app(\App\Services\GuidePlanningApi::class)->find($state);

                                    if (! $guide) {
                                        return;
                                    }

                                    $set('ticket_dispatch_guide_name', $guide['name']);

                                    if ($guide['dispatch_address']) {
                                        $set('ticket_dispatch_other', $guide['dispatch_address']);

                                        return;
                                    }

                                    if (blank($get('ticket_dispatch_other'))) {
                                        $coordinates = array_filter([
                                            $guide['name'],
                                            $guide['phone'] ? __('Tél. : :phone', ['phone' => $guide['phone']]) : null,
                                            $guide['email'],
                                        ]);
                                        $set('ticket_dispatch_other', implode("\n", $coordinates));
                                    }
                                })
                                ->columnSpanFull(),

                            Placeholder::make('ticket_dispatch_guide_notice')
                                ->hiddenLabel()
                                ->content(function (Get $get): ?HtmlString {
                                    if (! $get('ticket_dispatch_guide_id')) {
                                        return null;
                                    }

                                    $guide = app(\App\Services\GuidePlanningApi::class)->find($get('ticket_dispatch_guide_id'));

                                    if (($guide['dispatch_address'] ?? null) !== null) {
                                        return new HtmlString('<span style="color:#047857;font-size:0.8rem;">' . e(__('Adresse du guide récupérée automatiquement.')) . '</span>');
                                    }

                                    return new HtmlString('<span style="color:#b45309;font-size:0.8rem;">' . e(__('Aucune adresse n’est fournie par l’API pour ce guide : complétez l’adresse ou le lieu d’envoi ci-dessous.')) . '</span>');
                                })
                                ->visible(fn (Get $get): bool => $get('ticket_dispatch_method') === 'guide')
                                ->columnSpanFull(),

                            Textarea::make('ticket_dispatch_other')
                                ->label(fn (Get $get) => $get('ticket_dispatch_method') === 'guide' 
                                    ? __('Adresse / Coordonnées du guide') 
                                    : __('Adresse / Lieu d\'envoi alternatif'))
                                ->placeholder(fn (Get $get) => $get('ticket_dispatch_method') === 'guide' 
                                    ? __('Adresse complète, nom/hôtel du guide ou instructions d\'envoi...') 
                                    : __('Ex: Agence locale, Aéroport, point de rendez-vous...'))
                                ->rows(2)
                                ->required(fn (Get $get) => in_array($get('ticket_dispatch_method'), ['guide', 'autre']))
                                ->visible(fn (Get $get) => in_array($get('ticket_dispatch_method'), ['guide', 'autre']))
                                ->columnSpanFull(),

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

                    Section::make(__('Informations du Premier Hébergement'))->extraAttributes(['data-tk-anchor' => 'informations'])
                        ->columns(3)
                        ->schema([
                            Select::make('hotel_preset')
            ->label(__('🏢 Rechercher dans la base d\'hôtels'))
            ->placeholder(__('Tapez le nom d\'un hôtel pour pré-remplir...'))
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
                                    }
                                })
                                ->dehydrated(false)
                                ->columnSpanFull(),

                            TextInput::make('first_hotel_name')
                                ->label(__('Nom du premier hébergement'))
                ->placeholder(__('Ex: Hotel Gracery Shinjuku')),

                            DatePicker::make('first_hotel_check_in')
                                ->label(__('Date de check-in du 1er hébergement'))
                                ->native(false)
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
                                ->label(__('Adresse du premier hébergement'))
                                ->placeholder(__('Adresse complète pour l\'envoi de billetterie / wifi...'))
                                ->rows(1),
                        ]),

                    Section::make(__('Liste des Voyageurs'))->extraAttributes(['data-tk-anchor' => 'voyageurs'])
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
                                            ->native(false)
                                            ->required()
                                            ->live()
                                            ->maxDate(fn ($get) => $get('../../start_date') ? Carbon::parse($get('../../start_date'))->startOfDay() : now()->endOfDay())
                                            ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get)),

                                        TextInput::make('nationality')
                                            ->label(__('Nationalité'))
                ->placeholder(__('Ex: Française, Japonaise...'))
                                            ->required(),
                                    ])->columns(2),

                                    Textarea::make('dietary_restrictions')
                                        ->label(__('Allergies / restrictions alimentaires'))
                ->placeholder(__('Ex: Allergie arachides, sans porc, végétarien... Laissez vide si RAS.'))
                                        ->rows(2)
                                        ->columnSpanFull(),

                                    Textarea::make('mobility_concerns')
                                        ->label(__('Handicap / mobilité réduite'))
                ->placeholder(__('Ex: Fauteuil roulant, difficulty marches... Laissez vide si RAS.'))
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])
                        ]),
                ])->columnSpan(['lg' => 2]),

                // -------------------------------------------------------------
                // 2. COLONNE DE DROITE (Span 1)
                // -------------------------------------------------------------
                Group::make()->schema([
                    Section::make(__('Informations de Vol & Séjour au Japon'))->extraAttributes(['data-tk-anchor' => 'vol'])
                        ->description(__('Renseignez les dates globales du séjour ainsi que le détail des vols.'))
                        ->schema([
                            Group::make()->schema([
                                DatePicker::make('start_date')
                                    ->label(__('Date d\'arrivée au Japon'))
                                    ->native(false)
                                    ->required(fn (?Folder $record): bool => ! $record?->isInternal())
                                    ->live()
                                    ->beforeOrEqual(fn ($get) => filled($get('end_date')) ? 'end_date' : null)
                                    ->validationMessages([
                                        'before_or_equal' => __('L\'arrivée doit être avant ou le jour du départ.'),
                                    ])
                                    ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get)),

                                DatePicker::make('end_date')
                                    ->label(__('Date de départ'))
                                    ->native(false)
                                    ->required(fn (?Folder $record): bool => ! $record?->isInternal())
                                    ->live()
                                    ->afterOrEqual(fn ($get) => filled($get('start_date')) ? 'start_date' : null)
                                    ->validationMessages([
                                        'after_or_equal' => __('Le départ doit être après ou le jour de l\'arrivée.'),
                                    ])
                                    ->minDate(fn ($get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null),
                            ])->columns(2),

                            Textarea::make('flight_info')
                                ->label(__('Informations de vols'))
                ->placeholder(__('Ex: Vol AF276 Arrivée Haneda 10:30 / Vol Retour AF275...'))
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),

                    // Facturation agence : sans objet pour un dossier interne (finances internes au lot 6).
                    Section::make(__('Statut & Facturation'))->extraAttributes(['data-tk-anchor' => 'facturation'])
                        ->visible(fn (?Folder $record): bool => ! $record?->isInternal())
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
                                        $internalItems = $get('internalItems') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += (float) ($item['total_price'] ?? 0);
                                        }
                                        foreach ($internalItems as $item) {
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
                                        $internalItems = $get('internalItems') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += (float) ($item['purchase_total_price'] ?? 0);
                                        }
                                        foreach ($internalItems as $item) {
                                            $total += (float) ($item['purchase_total_price'] ?? 0);
                                        }
                                        return number_format($total, 0, '.', ' ') . ' ¥';
                                    }),

                                Placeholder::make('total_price_display')
                                    ->label(__('Prix de vente (Total)'))
                                    ->content(function ($get) {
                                        $items = $get('folderItems') ?? [];
                                        $internalItems = $get('internalItems') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += (float) ($item['total_price'] ?? 0);
                                        }
                                        foreach ($internalItems as $item) {
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
                                        $internalItems = $get('internalItems') ?? [];
                                        $totalSale = 0;
                                        $totalPurchase = 0;

                                        foreach ($items as $item) {
                                            $totalSale += (float) ($item['total_price'] ?? 0);
                                            $totalPurchase += (float) ($item['purchase_total_price'] ?? 0);
                                        }

                                        foreach ($internalItems as $item) {
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
                        
                    Section::make(__('📂 Documents du dossier'))->extraAttributes(['data-tk-anchor' => 'documents'])
                        ->description(fn (?Folder $record) => $record?->isInternal()
                            ? __('Fichiers internes du dossier (jamais visibles par une agence)')
                            : __('Fichiers partagés avec l\'agence'))
                        ->schema([
                            FileUpload::make('documents')
                                ->hiddenLabel()
                                ->multiple()
                                ->visible(fn (?Folder $record): bool => $record !== null)
                                ->helperText(__('Enregistrez d’abord le dossier : les documents seront ensuite classés automatiquement sur Google Drive.'))
                                ->getUploadedFileUsing(function (FileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                                    $folder = $component->getRecord();
                                    $storage = app(FolderDriveStorage::class);

                                    return $folder instanceof Folder
                                        ? $storage->uploadedFileInfo($folder, $file, $storedFileNames) ?? $component->getUploadedFile($file, $storedFileNames)
                                        : null;
                                })
                                ->saveUploadedFileUsing(function (FileUpload $component, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): ?string {
                                    $folder = $component->getRecord();

                                    return $folder instanceof Folder
                                        ? app(FolderDriveStorage::class)->upload($folder, $file, 'documents')
                                        : null;
                                })
                                ->deleteUploadedFileUsing(function (string $file): void {
                                    if (app(FolderDriveStorage::class)->isDrivePath($file)) {
                                        app(FolderDriveStorage::class)->delete($file);
                                    }
                                })
                                ->preserveFilenames()
                                ->downloadable()
                                ->openable()
                                ->reorderable()
                                ->acceptedFileTypes([
                                    'application/pdf', 
                                    'image/jpeg', 
                                    'image/png', 
                                    'application/msword', 
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                                ])
                                ->maxSize(10240),
                        ])
                        ->collapsible(),

                    Section::make(__('📥 Documents reçus de l’agence'))->extraAttributes(['data-tk-anchor' => 'documents'])
                        ->visible(fn (?Folder $record): bool => ! $record?->isInternal())
                        ->description(__('Tous les fichiers ajoutés par l’agence via les prestations ou la messagerie.'))
                        ->schema([
                            Placeholder::make('agency_submitted_documents')
                                ->hiddenLabel()
                                ->content(fn (?Folder $record): HtmlString => self::renderAgencySubmittedDocuments($record)),
                        ])
                        ->hidden(fn (?Folder $record): bool => $record === null)
                        ->collapsible(),
                        
                ])->columnSpan(['lg' => 1]),

                // -------------------------------------------------------------
                // 3. TOUTE LA LARGEUR (Full Width)
                // -------------------------------------------------------------
                
                // PRESTATIONS CLIENTS / AGENCE
                // Dossiers internes : les prestations passent par la préparation puis la commande (lot 3b).
                // Dossier interne : préparation libre, puis commande explicite des lignes choisies (lot 3b).
                PlannedItemsSection::make(),

                Section::make(__('Prestations commandées'))->extraAttributes(['data-tk-anchor' => 'prestations'])
                    ->description(fn (?Folder $record) => $record?->isInternal()
                        ? __('Prestations réellement commandées. Elles suivent le processus opérationnel habituel ; une annulation se fait par le statut « Annulé ».')
                        : __('Gérez les articles et options tarifaires liés à ce dossier. Pensez bien à sauvegarder le dossier après une modification !'))
                    ->columnSpanFull()
                    ->schema([
                        // Vue « jour par jour » (façon tableau Excel) au-dessus de la liste des fiches
                        FolderDayByDay::component(),

                        Repeater::make('folderItems')
                            ->relationship('folderItems')
                            // Dossier interne : aucune création ni suppression directe ici (préparation puis commande).
                            ->addable(fn (?Folder $record): bool => ! $record?->isInternal())
                            ->deletable(fn (?Folder $record): bool => ! $record?->isInternal())
                            ->hiddenLabel() 
                            ->addActionLabel(__('Ajouter une prestation au dossier'))
                            ->collapsible()
                            ->collapseAllAction(fn ($action) => $action->hidden())
                            ->expandAllAction(fn ($action) => $action->hidden())
                            ->collapsed()
                            ->live()
                            ->defaultItems(0)
                            ->itemLabel(function (array $state) {
                                if (!isset($state['product_id'])) return __('Nouvelle ligne de prestation');
                                
                                $productName = \App\Models\Product::find($state['product_id'])?->name ?? __('Produit inconnu');
                                $date = !empty($state['service_date']) ? Carbon::parse($state['service_date'])->format('d/m/Y') : '---';
                                $quantity = $state['quantity'] ?? 1;

                                $supplierText = "";
                                // Fournisseur actuel du produit (et non la copie figée sur la ligne)
                                $currentSupplierId = \App\Models\FolderItem::currentSupplierIdFor($state['product_id'], $state['supplier_id'] ?? null);
                                if ($currentSupplierId) {
                                    $supplier = \App\Models\Supplier::find($currentSupplierId);
                                    $supplierName = $supplier?->name ?? '';
                                    $supplierText = "  |  🏢 {$supplierName}";

                                    if ($supplier && $supplier->requires_invoice && empty($state['invoice_received_at'])) {
                                        $supplierText .= "  |  <span style='color: #ef4444; font-weight: bold;'>⚠️ En attente de facture</span>";
                                    }
                                } elseif (\App\Models\ProductSupplier::where('product_id', $state['product_id'])->count() > 1) {
                                    $supplierText = "  |  <span style='background-color: #fee2e2; color: #b91c1c; padding: 2px 8px; border-radius: 9999px; font-weight: bold; border: 1px solid #f87171;'>⚠️ " . e(__('SÉLECTIONNER UN FOURNISSEUR')) . "</span>";
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
                                    'success' => '#15803d', 
                                    'warning' => '#b45309', 
                                    'danger' => '#dc2626',  
                                    'info' => '#2563eb',    
                                    default => '#64748b',   
                                };

                                $mainText = "{$productName}  |  {$date}  |  {$optionName}  |  " . __('Qté : ') . $quantity . $supplierText;

                                // Consignes du commercial visibles sans déplier : rendez-vous, puis début de la note.
                                $where = trim(trim((string) ($state['meeting_time'] ?? '')) . ' ' . trim((string) ($state['meeting_point'] ?? '')));
                                if ($where !== '') {
                                    $mainText .= "  |  🕘 " . e(\Illuminate\Support\Str::limit($where, 60));
                                }
                                if (filled($state['ticketing_notes'] ?? null)) {
                                    $mainText .= "  |  📝 " . e(\Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', (string) $state['ticketing_notes']), 70));
                                }

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
                                    ->visible(function (array $arguments, \Filament\Forms\Components\Repeater $component): bool {
                                        $itemData = $component->getState()[$arguments['item']] ?? [];
                                        return \App\Models\FolderItem::supplierContactAvailable(
                                            ! empty($itemData['product_id']) ? (int) $itemData['product_id'] : null,
                                            ! empty($itemData['supplier_id']) ? (int) $itemData['supplier_id'] : null,
                                        )['mail'];
                                    })
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
                                    ->visible(function (array $arguments, \Filament\Forms\Components\Repeater $component): bool {
                                        $itemData = $component->getState()[$arguments['item']] ?? [];
                                        return \App\Models\FolderItem::supplierContactAvailable(
                                            ! empty($itemData['product_id']) ? (int) $itemData['product_id'] : null,
                                            ! empty($itemData['supplier_id']) ? (int) $itemData['supplier_id'] : null,
                                        )['fax'];
                                    })
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
                                        $item->custom_values = $itemData['custom_values'] ?? $itemData['custom_values'] ?? [];

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
                            ->schema(self::folderItemSchema('ordered'))
                    ]),

                // PRESTATIONS INTERNES (CONFIDENTIELLES - MASQUÉES AUX AGENCE)
                Section::make(__('🔒 Prestations Internes (Masquées aux agences)'))->extraAttributes(['data-tk-anchor' => 'internes'])
                    ->visible(fn (?Folder $record): bool => ! $record?->isInternal())
                    ->description(__('Saisissez des prestations ou frais internes. Ces éléments n\'apparaissent PAS côté agence ni sur les pré-factures, mais sont comptabilisés dans la marge Admin.'))
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Repeater::make('internalItems')
                            ->relationship('internalItems')
                            ->hiddenLabel()
                            ->addActionLabel(__('Ajouter une prestation interne'))
                            ->collapsible()
                            ->collapsed()
                            ->defaultItems(0)
                            ->live()
                            ->itemLabel(function (array $state) {
                                $title = $state['title'] ?? __('Prestation sans titre');
                                $qty = $state['quantity'] ?? 1;
                                $purchase = number_format((float)($state['purchase_total_price'] ?? 0), 0, '.', ' ');
                                return "🔒 {$title}  |  Quantité : {$qty}  |  Achat : {$purchase} ¥";
                            })
                            ->schema([
                                Hidden::make('is_internal')->default(true)->dehydrated(true),

                                Group::make()->schema([
                                    TextInput::make('title')
                                        ->label(__('Titre / Description libre'))
            ->placeholder(__('Ex: Transport privé local, Frais de livraison, Guide supplémentaire...'))
                                        ->required()
                                        ->columnSpan(4),

                                    DatePicker::make('service_date')
                                        ->label(__('Date'))
                                        ->native(false)
                                        ->live()
                                        ->columnSpan(2),

                                    TextInput::make('quantity')
                                        ->label(__('Quantité'))
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->required()
                                        ->live()
                                        ->columnSpan(2),

                                    TextInput::make('purchase_total_price')
                                        ->label(__('Total Achat (¥)'))
                                        ->numeric()
                                        ->default(0)
                                        ->required()
                                        ->live()
                                        ->columnSpan(2),

                                    Select::make('item_status_id')
                                        ->relationship('itemStatus', 'name')
                                        ->label(__('Statut'))
                                        ->searchable()
                                        ->preload()
                                        ->default(fn () => \App\Models\ItemStatus::where('name', 'En attente de validation')->value('id'))
                                        ->columnSpan(2),
                                ])->columns(12),
                            ])
                    ]),
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

                TextColumn::make('origin')
                    ->label(__('Origine'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === Folder::ORIGIN_INTERNAL ? __('Interne') : __('Agence'))
                    ->color(fn (?string $state) => $state === Folder::ORIGIN_INTERNAL ? 'warning' : 'gray'),

                TextColumn::make('agency.name')
                    ->label(__('Agence'))
                    // Dossier interne : agence associée à titre informatif.
                    ->getStateUsing(fn (Folder $record) => $record->isInternal()
                        ? ($record->clientAgency?->name ? $record->clientAgency->name . ' ' . __('(info)') : null)
                        : $record->agency?->name)
                    ->sortable(),

                TextColumn::make('owner.name')
                    ->label(__('Responsable'))
                    ->toggleable(),

                TextColumn::make('folder_name')
                    ->label(__('Nom du dossier'))
                    ->searchable(),

                TextColumn::make('mainSeller.name')
                    ->label(__('Vendeur Principal'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('segment')
                    ->label(__('Segment'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Folder::SEGMENTS[$state] ?? __('Non classé'))
                    ->color(fn (?string $state) => $state ? 'info' : 'gray'),

                TextColumn::make('ops_status')
                    ->label(__('Avancement (interne)'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Folder::OPS_STATUSES[$state] ?? '')
                    ->toggleable(),

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
                    ->getStateUsing(function ($record) {
                        $itemsTotal = $record->billableFolderItems()->sum('total_price');
                        $fee = (float) ($record->folder_fee ?? 0);
                        
                        // 💡 Le rattrapage intelligent pour les brouillons :
                        if ($fee === 0.0 && $record->status === 'draft') {
                            $fee = (float) ($record->agency?->clientGroup?->folder_fee ?? 0);
                        }
                        
                        return $itemsTotal + $fee;
                    })
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('segment')
                    ->label(__('Segment'))
                    ->options(Folder::SEGMENTS),
                \Filament\Tables\Filters\SelectFilter::make('origin')
                    ->label(__('Origine'))
                    ->options([Folder::ORIGIN_INTERNAL => __('Interne'), Folder::ORIGIN_B2B => __('Agence (B2B)')]),
                \Filament\Tables\Filters\SelectFilter::make('kind')
                    ->label(__('Type'))
                    ->options(Folder::KINDS),
                \Filament\Tables\Filters\SelectFilter::make('ops_status')
                    ->label(__('Avancement (interne)'))
                    ->options(Folder::OPS_STATUSES),
                \Filament\Tables\Filters\SelectFilter::make('owner_user_id')
                    ->label(__('Commercial responsable'))
                    ->relationship('owner', 'name'),
                \Filament\Tables\Filters\SelectFilter::make('any_agency')
                    ->label(__('Agence (propriétaire ou associée)'))
                    ->options(fn () => \App\Models\Agency::orderBy('name')->pluck('name', 'id'))
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->where(fn ($q) => $q->where('agency_id', $data['value'])->orWhere('client_agency_id', $data['value']))
                        : $query),
                \Filament\Tables\Filters\Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('Voyage à partir du')),
                        DatePicker::make('until')->label(__('Voyage jusqu’au')),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('end_date', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('start_date', '<=', $d))),
            ])
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
            'create-internal' => Pages\CreateInternalFolder::route('/nouveau-dossier-interne'),
            'edit' => Pages\EditFolder::route('/{record}/edit'),
        ];
    }
}
