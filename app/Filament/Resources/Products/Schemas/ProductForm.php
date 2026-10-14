<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // -------------------------------------------------------------
                // COLONNE GAUCHE (Principale - Largeur 2/3)
                // -------------------------------------------------------------
                Group::make()->schema([
                    
                    Section::make(__('Présentation de la prestation'))
                        ->description(__('Renseignez le titre, la description détaillée et ajoutez les visuels.'))
                        ->schema([
                            TextInput::make('name')
                                ->label(__('Nom du produit / Prestation'))
                                ->placeholder(__('Ex: Location de Kimono à Kyoto / Billet Shinkansen'))
                                ->required()
                                ->columnSpanFull(),

                            TextInput::make('guide_city')
                                ->label(__('Ville de la visite guidée'))
                                ->helperText(__('Obligatoire pour créer automatiquement une mission Planning Guides.'))
                                ->visible(fn (Get $get): bool => (int) $get('category_id') === 4)
                                ->required(fn (Get $get): bool => (int) $get('category_id') === 4)
                                ->columnSpanFull(),

                            Textarea::make('description')
                                ->label(__('Description commerciale'))
                                ->placeholder(__('Décrivez précisément l\'activité ou les spécificités du produit...'))
                                ->rows(5)
                                ->columnSpanFull(),

                            FileUpload::make('images')
                                ->label(__('Galerie de photos d\'illustration'))
                                ->multiple()
                                ->image()
                                ->reorderable()
                                ->panelLayout('grid')
                                ->disk('public')
                                ->directory('products')
                                ->columnSpanFull(),
                        ]),

                    Section::make(__('Informations importantes'))
                        ->description(__('Documents à lire par l\'agence avant d\'ajouter la prestation à un dossier (optionnel).'))
                        ->schema([
                            Repeater::make('important_documents')
                                ->hiddenLabel()
                                ->addActionLabel(__('Ajouter un document'))
                                ->itemLabel(fn (array $state): ?string => $state['label'] ?? __('Nouveau document'))
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    TextInput::make('label')
                                        ->label(__('Libellé du document'))
                                        ->placeholder(__('Ex: Conditions de l\'activité'))
                                        ->required(),

                                    FileUpload::make('file')
                                        ->label(__('Fichier (PDF ou autre)'))
                                        ->disk('public')
                                        ->directory('products/important-documents')
                                        ->preserveFilenames()
                                        ->required(),
                                ])
                                ->columns(2),
                        ])
                        ->collapsible()
                        ->collapsed(),

                    Section::make(__('Informations requises lors de l\'achat'))
                        ->description(__('Configurez les questions spécifiques que l\'agence devra remplir pour valider la réservation.'))
                        ->schema([
                            Repeater::make('custom_field_definitions')
                                ->hiddenLabel() 
                                ->addActionLabel(__('Demander une information spécifique'))
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? __('Nouveau champ requis'))
                                ->collapsible()
                                ->collapsed() 
                                ->schema([
                                    Group::make()->schema([
                                        TextInput::make('name')
                                            ->label(__('Question affichée'))
                                            ->placeholder(__('Ex: Taille en cm'))
                                            ->required(),

                                        TextInput::make('key')
                                            ->label(__('Clé (Shortcode)'))
                                            ->placeholder(__('Ex: taille'))
                                            ->regex('/^[a-zA-Z0-9_-]+$/')
                                            ->helperText(__('Sans espace (ex: [CUSTOM:taille])'))
                                            ->required(),

                                        Select::make('type')
                                            ->label(__('Format de la réponse'))
                                            ->options([
                                                'text' => __('Texte court'),
                                                'textarea' => __('Texte long'),
                                                'number' => __('Nombre entier'),
                                                'date' => __('Date'),
                                                'toggle' => __('Case à cocher (Oui/Non)'),
                                                'select' => __('Liste de choix (Menu déroulant)'),
                                                'file' => __('Fichier joint (Image / PDF)'), 
                                                'train_station' => __('Gare de Train (Autocomplétion)'), // 💡 NOUVEAU
                                                'bus_station' => __('Station de Bus (Autocomplétion)'), // 💡 NOUVEAU
                                                'client_hotel' => __('Hôtel du client (Autocomplétion + saisie libre)'),
                                            ])
                                            ->live()
                                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                                if ($state === 'client_hotel') {
                                                    $set('is_per_passenger', false);
                                                }
                                            })
                                            ->required(),
                                    ])->columns(3),

                                    TagsInput::make('choices')
                                        ->label(__('Options proposées (Appuyez sur Entrée après chaque choix)'))
                                        ->placeholder(__('Ex: S, M, L, XL'))
                                        ->visible(fn (Get $get) => $get('type') === 'select')
                                        ->required(fn (Get $get) => $get('type') === 'select')
                                        ->columnSpanFull(),

                                    Group::make()->schema([
                                        TextInput::make('placeholder')
                                            ->label(__('Exemple d\'aide (Placeholder)'))
                                            ->placeholder(__('Ex: M, L, XL ou 175cm...'))
                                            ->visible(fn (Get $get) => in_array($get('type'), ['text', 'textarea', 'number', 'select', 'train_station', 'bus_station', 'client_hotel'])),
                                        Toggle::make('is_required')
                                            ->label(__('Rendre obligatoire'))
                                            ->default(true)
                                            ->inline(false),
                                    ])->columns(2),

                                    Toggle::make('is_per_passenger')
                                        ->label(__('Multiplier cette question par le nombre de voyageurs / quantité'))
                                        ->helperText(__('Si coché, et que l\'agence achète 4 unités, le système générera automatiquement 4 lignes de saisie distinctes en Front-Office.'))
                                        ->default(false)
                                        ->disabled(fn (Get $get): bool => $get('type') === 'client_hotel')
                                        ->columnSpanFull(),
                                ])
                        ]),

                    Section::make(__('Déclinaisons & Options tarifaires'))
                        ->description(__('Chaque ligne représente un choix. Utilisez le MÊME nom de groupe (ex: "Montant Carte IC") pour créer un menu déroulant à choix unique.'))
                        ->schema([
                            Repeater::make('productOptions')
                                ->relationship()
                                ->hiddenLabel()
                                ->addActionLabel(__('Ajouter un choix / déclinaison / option'))
                                ->itemLabel(fn (array $state): ?string => isset($state['name']) 
                                    ? (!empty($state['group_name']) ? '[' . $state['group_name'] . '] ' : '') . $state['name'] . ' (+' . ($state['price_modifier'] ?? 0) . ' ¥)'
                                    : __('Nouveau choix'))
                                ->collapsible()
                                ->collapsed(true) // 💡 Replié par défaut pour économiser l'espace
                                ->cloneable()
                                ->reorderable(true)
                                ->columns(12) // 💡 Disposition horizontale ultra-compacte
                                ->schema([
                                    TextInput::make('group_name')
                                        ->label(__('Groupe / Déclinaison'))
                                        ->placeholder(__('Ex: Montant Carte IC'))
                                        ->columnSpan(['default' => 12, 'md' => 3]),

                                    TextInput::make('name')
                                        ->label(__('Nom / Valeur'))
                                        ->placeholder(__('Ex: 2 000 ¥'))
                                        ->required()
                                        ->columnSpan(['default' => 12, 'md' => 3]),

                                    TimePicker::make('guide_start_time')
                                        ->label(__('Début guidage'))
                                        ->helperText(__('Visible uniquement pour les produits de guidage.'))
                                        ->seconds(false)
                                        ->visible(fn (Get $get, $livewire): bool => (int) ($get('../../category_id') ?? data_get($livewire, 'record.category_id')) === 4)
                                        ->required(fn (Get $get, $livewire): bool => (int) ($get('../../category_id') ?? data_get($livewire, 'record.category_id')) === 4)
                                        ->columnSpan(['default' => 6, 'md' => 2]),

                                    TimePicker::make('guide_end_time')
                                        ->label(__('Fin guidage'))
                                        ->helperText(__('Visible uniquement pour les produits de guidage.'))
                                        ->seconds(false)
                                        ->visible(fn (Get $get, $livewire): bool => (int) ($get('../../category_id') ?? data_get($livewire, 'record.category_id')) === 4)
                                        ->required(fn (Get $get, $livewire): bool => (int) ($get('../../category_id') ?? data_get($livewire, 'record.category_id')) === 4)
                                        ->columnSpan(['default' => 6, 'md' => 2]),

                                    TextInput::make('price_modifier')
                                        ->label(__('Supplément (¥)'))
                                        ->numeric()
                                        ->default(0)
                                        ->required()
                                        ->placeholder('0')
                                        ->columnSpan(['default' => 12, 'md' => 2]),

                                    TextInput::make('adult_price_modifier')
                                        ->label(__('Prix adulte (¥)'))
                                        ->numeric()
                                        ->placeholder(__('Hérité du supplément général'))
                                        ->helperText(__('Utilisé lorsqu’un sélecteur d’âge est activé.'))
                                        ->visible(fn (Get $get): bool => ((bool) $get('../../enable_child_selector') || (bool) $get('../../enable_teen_selector')) && $get('billing_type') === 'per_pax')
                                        ->columnSpan(['default' => 12, 'md' => 2]),

                                    TextInput::make('child_price_modifier')
                                        ->label(__('Prix enfant (¥)'))
                                        ->numeric()
                                        ->placeholder(__('Hérité du supplément général'))
                                        ->helperText(__('Utilisé si le sélecteur enfant est activé.'))
                                        ->visible(fn (Get $get): bool => (bool) $get('../../enable_child_selector') && $get('billing_type') === 'per_pax')
                                        ->columnSpan(['default' => 12, 'md' => 2]),

                                    TextInput::make('teen_price_modifier')
                                        ->label(__('Prix adolescent (¥)'))
                                        ->numeric()
                                        ->placeholder(__('Hérité du supplément général'))
                                        ->helperText(__('Utilisé si le sélecteur adolescent est activé.'))
                                        ->visible(fn (Get $get): bool => (bool) $get('../../enable_teen_selector') && $get('billing_type') === 'per_pax')
                                        ->columnSpan(['default' => 12, 'md' => 2]),

                                    Select::make('billing_type')
                                        ->label(__('Facturation'))
                                        ->options([
                                            'per_pax' => __('Par Pax'),
                                            'per_booking' => __('Fixe Dossier'),
                                            'manual' => __('Qté Libre'),
                                        ])
                                        ->default('per_pax')
                                        ->required()
                                        ->columnSpan(['default' => 12, 'md' => 2]),

                                    Toggle::make('is_required')
                                        ->label(__('Obligatoire'))
                                        ->default(false)
                                        ->inline(false)
                                        ->columnSpan(['default' => 12, 'md' => 2]),

                                    TextInput::make('code')
                                        ->label(__('Clé Shortcode Email'))
                                        ->placeholder(__('Ex: ic_2000'))
                                        ->columnSpanFull(),

                                    Repeater::make('seasonalPrices')
                                        ->relationship()
                                        ->label(__('Tarifs par saison'))
                                        ->helperText(__('Guidage uniquement : choisissez une saison, puis renseignez le supplément de chaque tranche affichée automatiquement.'))
                                        ->visible(fn (Get $get, $livewire): bool => (int) ($get('../../category_id') ?? data_get($livewire, 'record.category_id')) === 4)
                                        ->table([
                                            TableColumn::make(__('Saison'))->width('135px')->markAsRequired(),
                                            TableColumn::make(__('Tranche tarifaire'))->width('190px')->markAsRequired(),
                                            TableColumn::make(__('Vente & coût d’achat')),
                                        ])
                                        ->compact()
                                        ->addActionLabel(__('Ajouter un tarif saisonnier'))
                                        ->schema([
                                            Select::make('product_period_id')
                                                ->label(__('Saison'))
                                                ->options(function ($livewire): array {
                                                    $product = data_get($livewire, 'record');

                                                    return $product
                                                        ? $product->productPeriods()->orderBy('name')->pluck('name', 'id')->all()
                                                        : [];
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->live()
                                                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                                    if (! $state) {
                                                        return;
                                                    }

                                                    $tiers = \App\Models\ProductPrice::query()
                                                        ->where('product_period_id', $state)
                                                        ->orderBy('min_pax')
                                                        ->get(['id']);

                                                    if ($tiers->isEmpty()) {
                                                        return;
                                                    }

                                                    $rows = $get('../../seasonalPrices') ?? [];
                                                    $firstTierId = (int) $tiers->first()->id;
                                                    $assignedCurrentRow = false;

                                                    foreach ($rows as &$row) {
                                                        if (! $assignedCurrentRow
                                                            && (int) ($row['product_period_id'] ?? 0) === (int) $state
                                                            && blank($row['product_price_id'] ?? null)) {
                                                            $row['product_price_id'] = $firstTierId;
                                                            $assignedCurrentRow = true;
                                                        }
                                                    }
                                                    unset($row);

                                                    $existingTierIds = collect($rows)
                                                        ->filter(fn (array $row): bool => (int) ($row['product_period_id'] ?? 0) === (int) $state)
                                                        ->pluck('product_price_id')
                                                        ->filter()
                                                        ->map(fn ($id): int => (int) $id)
                                                        ->all();

                                                    foreach ($tiers as $tier) {
                                                        if (! in_array($tier->id, $existingTierIds, true)) {
                                                            $rows[] = [
                                                                'product_period_id' => $state,
                                                                'product_price_id' => $tier->id,
                                                            ];
                                                        }
                                                    }

                                                    $set('../../seasonalPrices', array_values($rows));
                                                })
                                                ->required(),
                                            Select::make('product_price_id')
                                                ->label(__('Tranche tarifaire'))
                                                ->options(function (Get $get): array {
                                                    $periodId = $get('product_period_id');

                                                    return $periodId
                                                        ? \App\Models\ProductPrice::query()
                                                            ->where('product_period_id', $periodId)
                                                            ->orderBy('min_pax')
                                                            ->get()
                                                            ->mapWithKeys(function ($price): array {
                                                                $label = $price->pricing_mode === 'extra_pax'
                                                                    ? __(':min+ pax — supplément :price ¥', ['min' => $price->min_pax, 'price' => number_format($price->price, 0, '.', ' ')])
                                                                    : __(':min–:max pax — :price ¥', ['min' => $price->min_pax, 'max' => $price->max_pax, 'price' => number_format($price->price, 0, '.', ' ')]);

                                                                return [$price->id => $label];
                                                            })
                                                            ->all()
                                                        : [];
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->required(),
                                            Group::make()
                                                ->schema([
                                                    TextInput::make('adult_price_modifier')
                                                        ->label(__('Adulte (¥)'))
                                                        ->numeric()
                                                        ->placeholder(__('Adulte')),
                                                    TextInput::make('purchase_price_modifier')
                                                        ->label(__('Coût d’achat (¥)'))
                                                        ->numeric()
                                                        ->placeholder(__('Achat')),
                                                    TextInput::make('child_price_modifier')
                                                        ->label(__('Enfant (¥)'))
                                                        ->numeric()
                                                        ->placeholder(__('Enfant'))
                                                        ->visible(fn (Get $get, $livewire): bool => (bool) ($get('../../../../enable_child_selector') ?? data_get($livewire, 'record.enable_child_selector'))),
                                                    TextInput::make('teen_price_modifier')
                                                        ->label(__('Adolescent (¥)'))
                                                        ->numeric()
                                                        ->placeholder(__('Ado'))
                                                        ->visible(fn (Get $get, $livewire): bool => (bool) ($get('../../../../enable_teen_selector') ?? data_get($livewire, 'record.enable_teen_selector'))),
                                                ])
                                                ->columns(3),
                                        ])->columns(2)
                                        ->columnSpanFull(),
                                ])
                        ]),

                    Section::make(__('Calendrier & Grilles Tarifaires (Prix NETS)'))
                        ->description(__('Définissez vos saisons annuelles de validité, l\'âge limite des enfants et vos prix.'))
                        ->schema([
                            TextInput::make('child_age_limit')
                                ->label(__('Âge maximum enfant / borne basse adolescent (Inclus)'))
                                ->helperText(__('Cette borne définit la tranche enfant, ou le début de la tranche adolescent si seul ce sélecteur est activé.'))
                                ->numeric()
                                ->default(11)
                                ->required(fn (Get $get): bool => (bool) $get('enable_child_selector'))
                                ->visible(fn (Get $get): bool => (bool) $get('enable_child_selector')),

                            TextInput::make('teen_age_limit')
                                ->label(__('Âge maximum pour être considéré adolescent (Inclus)'))
                                ->helperText(__('L’adolescent est compris entre la limite enfant + 1 et cette limite. Laissez vide pour conserver seulement adultes et enfants.'))
                                ->numeric()
                                ->minValue(fn (Get $get): int => (int) ($get('child_age_limit') ?? 11) + 1)
                                ->visible(fn (Get $get): bool => (bool) $get('enable_teen_selector'))
                                ->live(),

                            Toggle::make('enable_child_selector')
                                ->label(__('Activer le sélecteur Enfants'))
                                ->helperText(__('Permet à l’agence de saisir séparément le nombre d’adultes et d’enfants.'))
                                ->default(false)
                                ->live(),

                            Toggle::make('enable_teen_selector')
                                ->label(__('Activer le sélecteur Adolescents'))
                                ->helperText(__('Permet à l’agence de saisir séparément le nombre d’adultes et d’adolescents.'))
                                ->default(false)
                                ->live(),

                            Repeater::make('productPeriods')
                                ->relationship()
                                ->hiddenLabel() 
                                ->collapsible()
                                ->collapsed(fn (Get $get, $livewire): bool => (int) (data_get($livewire, 'record.category_id') ?? $get('category_id')) !== 4)
                                ->cloneable()
                                ->addActionLabel(__('Créer une nouvelle période / saison'))
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? __('Nouvelle Saison'))
                                ->schema([
                                    TextInput::make('name')
                                        ->label(__('Nom de la saison (ex: Haute saison)'))
                                        ->placeholder(__('Ex: Golden Week'))
                                        ->columnSpanFull()
                                        ->required(),
                                        
                                    Repeater::make('date_ranges')
                                        ->label(__('Plages de validité de cette saison'))
                                        ->helperText(__('Ajoutez plusieurs plages lorsque la même grille tarifaire s’applique à différentes périodes de l’année.'))
                                        ->defaultItems(1)
                                        ->addActionLabel(__('Ajouter une plage de dates'))
                                        ->schema([
                                            TextInput::make('start_date')
                                                ->label(__('Date de début (JJ/MM)'))
                                                ->placeholder('Ex: 01/04')
                                                ->mask('99/99')
                                                ->rules([function () {
                                                    return function (string $attribute, $value, \Closure $fail) {
                                                        $parts = explode('/', (string) $value);
                                                        if (count($parts) !== 2 || ! checkdate((int) ($parts[1] ?? 0), (int) ($parts[0] ?? 0), 2024)) {
                                                            $fail(__('Date de début invalide (format JJ/MM attendu).'));
                                                        }
                                                    };
                                                }])
                                                ->formatStateUsing(fn (?string $state) => $state && str_contains($state, '-') ? implode('/', array_reverse(explode('-', $state))) : $state)
                                                ->dehydrateStateUsing(fn (?string $state) => $state && str_contains($state, '/') ? implode('-', array_reverse(explode('/', $state))) : $state)
                                                ->required(),
                                            TextInput::make('end_date')
                                                ->label(__('Date de fin (JJ/MM)'))
                                                ->placeholder('Ex: 31/10')
                                                ->mask('99/99')
                                                ->rules([function () {
                                                    return function (string $attribute, $value, \Closure $fail) {
                                                        $parts = explode('/', (string) $value);
                                                        if (count($parts) !== 2 || ! checkdate((int) ($parts[1] ?? 0), (int) ($parts[0] ?? 0), 2024)) {
                                                            $fail(__('Date de fin invalide (format JJ/MM attendu).'));
                                                        }
                                                    };
                                                }])
                                                ->formatStateUsing(fn (?string $state) => $state && str_contains($state, '-') ? implode('/', array_reverse(explode('-', $state))) : $state)
                                                ->dehydrateStateUsing(fn (?string $state) => $state && str_contains($state, '/') ? implode('-', array_reverse(explode('/', $state))) : $state)
                                                ->required(),
                                        ])->columns(2),
                                    
                                    Repeater::make('productPrices')
                                        ->relationship()
                                        ->label(__('Grille tarifaire (Pax & Âges)'))
                                        ->collapsible()
                                        ->collapsed(fn (Get $get, $livewire): bool => (int) (data_get($livewire, 'record.category_id') ?? $get('../../category_id')) !== 4)
                                        ->addActionLabel(__('Ajouter un palier de prix'))
                                        ->itemLabel(fn (array $state): ?string => isset($state['price']) ? __('Tarif : ') . $state['price'] . ' ¥' : __('Nouveau tarif'))
                                        ->schema([
                                            Group::make()->schema([
                                                TextInput::make('min_pax')->label(__('Pax Min'))->numeric()->default(1),
                                                TextInput::make('max_pax')->label(__('Pax Max'))->numeric()->default(99),
                                            ])->columns(2),
                                            
                                            Group::make()->schema([
                                                TextInput::make('min_age')->label(__('Âge Min'))->numeric()->default(0),
                                                TextInput::make('max_age')->label(__('Âge Max'))->numeric()->default(99),
                                            ])->columns(2),

                                            TextInput::make('price')
                                                ->label(fn (Get $get): string => $get('pricing_mode') === 'extra_pax'
                                                    ? __('Supplément par pax (¥)')
                                                    : __('Prix net (¥)'))
                                                ->helperText(fn (Get $get): ?string => $get('pricing_mode') === 'extra_pax'
                                                    ? __('Cette ligne est ajoutée au tarif de base pour chaque pax inclus dans sa tranche.')
                                                    : null)
                                                ->placeholder('0')
                                                ->numeric()
                                                ->required(),

                                            Select::make('pricing_mode')
                                                ->label(__('Type de tarif'))
                                                ->options([
                                                    'per_pax' => __('Par pax'),
                                                    'global' => __('Forfait global par tranche'),
                                                    'extra_pax' => __('Supplément par pax supplémentaire'),
                                                ])
                                                ->default('per_pax')
                                                ->live()
                                                ->visible(fn (Get $get, $livewire): bool => (int) ($get('../../../../category_id') ?? data_get($livewire, 'record.category_id')) === 4)
                                                ->required(),

                                            TextInput::make('purchase_price')
                                                ->label(fn (Get $get): string => $get('pricing_mode') === 'extra_pax'
                                                    ? __('Coût d’achat par pax supplémentaire (¥)')
                                                    : __('Coût d’achat (¥)'))
                                                ->helperText(fn (Get $get): string => $get('pricing_mode') === 'extra_pax'
                                                    ? __('Coût interne appliqué pour chaque pax supplémentaire de cette tranche.')
                                                    : __('Information interne, non visible par les agences.'))
                                                ->numeric()
                                                ->minValue(0)
                                                ->visible(fn (Get $get, $livewire): bool => (int) ($get('../../../../category_id') ?? data_get($livewire, 'record.category_id')) === 4),

                                        ])->columns(3)
                                ])
                        ]),

                    Section::make(__('Modèles de communication par Fournisseur'))
                        ->description(__('Personnalisez les e-mails et fax pour chaque fournisseur rattaché. La liste ci-dessous se met à jour automatiquement en fonction des fournisseurs cochés dans la colonne de droite.'))
                        ->schema([
                            Repeater::make('productSuppliers')
                                ->relationship()
                                ->hiddenLabel() 
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->itemLabel(fn (array $state): ?string => isset($state['supplier_id']) ? \App\Models\Supplier::find($state['supplier_id'])?->name : __('Fournisseur'))
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    Select::make('supplier_id')
                                        ->label(__('Fournisseur concerné'))
                                        ->options(fn() => \App\Models\Supplier::pluck('name', 'id'))
                                        ->disabled()
                                        ->dehydrated()
                                        ->required(),

                                    TextInput::make('email_subject')
                                        ->label(__('Objet de l\'e-mail / Sujet du Fax'))
                                        ->placeholder("Ex: ご予約依頼 : [DOSSIER_REF] / [LEAD_NAME]")
                                        ->columnSpanFull(),

                                    Group::make()
                                        ->statePath('fax_header')
                                        ->schema([
                                    Section::make(__('En-tête visuelle du document FAX'))
                                                ->collapsed() 
                                                ->columns(2)
                                                ->schema([
                                                    Group::make()->schema([
                                                        Placeholder::make('lbl_to')
                                                            ->hiddenLabel()
                                                            ->content(new \Illuminate\Support\HtmlString('<div style="font-weight:bold; font-size:1.1rem; border-bottom: 2px solid #e5e7eb; margin-bottom: 0.5rem; padding-bottom: 0.2rem;">送付先： (Destinataire)</div>')),
                                                        TextInput::make('to_company_name')
                                                            ->label(__('Nom de l\'entreprise'))
                                                            ->placeholder('Ex: 旭川合同自動車㈱ タクシーコールセンター'),
                                                        TextInput::make('to_contact_name')
                                                            ->label(__('Nom du contact'))
                                                            ->default('ご担当者様')
                                                            ->placeholder('Ex: 田中様'),
                                                        TextInput::make('to_tel')
                                                            ->label(__('TEL Destinataire'))
                                                            ->placeholder('Ex: 0166-33-3131'),
                                                        TextInput::make('to_fax')
                                                            ->label(__('FAX Destinataire'))
                                                            ->placeholder('Ex: 0166-34-0930'),
                                                    ])->columnSpan(1),

                                                    Group::make()->schema([
                                                        Placeholder::make('lbl_from')
                                                            ->hiddenLabel()
                                                            ->content(new \Illuminate\Support\HtmlString('<div style="font-weight:bold; font-size:1.1rem; border-bottom: 2px solid #e5e7eb; margin-bottom: 0.5rem; padding-bottom: 0.2rem;">発信元： (Expéditeur)</div>')),
                                                        TextInput::make('from_company')
                                                            ->label(__('Société émettrice'))
                                                            ->default('TAKADA TRAVEL合同会社')
                                                            ->placeholder('Ex: TAKADA TRAVEL...'),
                                                        Textarea::make('from_address')
                                                            ->label(__('Adresse postale'))
                                                            ->default("〒532-0012大阪市淀川区木川東\n3丁目1-23 KC新大阪ビル 2階")
                                                            ->rows(2)
                                                            ->placeholder("〒532-0012..."),
                                                        TextInput::make('from_contact')
                                                            ->label(__('Contact expéditeur'))
                                                            ->default('担当者： [NOM_AGENT]')
                                                            ->helperText(__('Laissez [NOM_AGENT] pour utiliser le nom du compte connecté.'))
                                                            ->placeholder('担当者： [NOM_AGENT]'),
                                                        TextInput::make('from_mail')
                                                            ->label(__('Email de réponse'))
                                                            ->default('MAIL : [EMAIL_AGENT]')
                                                            ->helperText(__('Laissez [EMAIL_AGENT] pour utiliser l\'email du compte connecté.'))
                                                            ->placeholder('MAIL : [EMAIL_AGENT]'),
                                                        TextInput::make('from_tel')
                                                            ->label(__('TEL émetteur'))
                                                            ->default('TEL：06-6195-9799')
                                                            ->placeholder('TEL：06-6195-9799'),
                                                        TextInput::make('from_fax')
                                                            ->label(__('FAX émetteur'))
                                                            ->default('FAX：06-6195-9921')
                                                            ->placeholder('FAX：06-6195-9921'),
                                                    ])->columnSpan(1),
                                                ]),
                                        ]),

                                    Textarea::make('email_template')
                                        ->label(__('Corps du message (Valable pour Mail et Fax)'))
                                        ->placeholder("Bonjour [CONTACT_FOURNISSEUR],\n\nJe souhaite réserver la prestation suivante...\n\n[IF_QUANTITY>=10]Attention c'est un grand groupe ![/IF_QUANTITY]\n\n[IF_PAX_CHILDREN>0]Parmi eux, il y a [PAX_CHILDREN] enfants ![/IF_PAX_CHILDREN]\n\n[IF_OPTION:dressing]Options incluses : Habillage pour [OPTION:dressing] personnes.[/IF_OPTION]\n\nCordialement,\n[NOM_AGENT]")
                                        ->rows(10)
                                        ->columnSpanFull(),
                                ]),

                            Section::make(__('Aide : Liste des Shortcodes & Moteur Logique'))
                                ->icon('heroicon-o-information-circle')
                                ->collapsed() 
                                ->compact()
                                ->schema([
                                    Placeholder::make('shortcodes_help')
                                        ->hiddenLabel()
                                        ->content(new \Illuminate\Support\HtmlString('
                                            <div class="text-sm text-gray-600">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <div>
                                                        <strong class="text-primary-600 block mb-2">📋 Variables Générales</strong>
                                                        <ul class="list-disc pl-5 space-y-1">
                                                            <li><code class="font-mono bg-gray-100 px-1 py-0.5 rounded border border-gray-200">[DOSSIER_REF]</code> : Réf du dossier</li>
                                                            <li><code class="font-mono bg-gray-100 px-1 py-0.5 rounded border border-gray-200">[LEAD_NAME]</code> : Nom du voyageur</li>
                                                            <li><code class="font-mono bg-gray-100 px-1 py-0.5 rounded border border-gray-200">[DATE_PRESTA]</code> : Date (12/04/2026)</li>
                                                            <li><code class="font-mono bg-gray-100 px-1 py-0.5 rounded border border-gray-200">[DATE_PRESTA_JP]</code> : Date (2026年04月12日)</li>
                                                            <li><code class="font-mono bg-gray-100 px-1 py-0.5 rounded border border-gray-200">[QUANTITE]</code> : Quantité totale de pax</li>
                                                            <li><code class="font-mono bg-gray-100 px-1 py-0.5 rounded border border-gray-200">[PAX_ADULTS]</code> : Nombre d\'adultes calculé</li>
                                                            <li><code class="font-mono bg-gray-100 px-1 py-0.5 rounded border border-gray-200">[PAX_CHILDREN]</code> : Nombre d\'enfants calculé</li>
                                                            <li><code class="font-mono bg-gray-100 px-1 py-0.5 rounded border border-gray-200">[PAX_TEENAGERS]</code> : Nombre d\'adolescents calculé</li>
                                                            <li><code class="font-mono bg-gray-100 px-1 py-0.5 rounded border border-gray-200">[OPTION_NAME]</code> : Options choisies</li>
                                                            <li><code class="font-mono bg-gray-100 px-1 py-0.5 rounded border border-gray-200">[LISTE_PASSAGERS]</code> : Tableau (Nom, Age)</li>
                                                        </ul>
                                                    </div>
                                                    <div>
                                                        <strong class="text-primary-600 block mb-2">🎯 Valeurs ciblées & Logiques</strong>
                                                        <ul class="list-disc pl-5 space-y-1">
                                                            <li><code class="font-mono bg-gray-100 px-1 py-0.5 rounded border border-gray-200">[CUSTOM:Clé]</code> : Affiche la réponse du client.</li>
                                                            <li><code class="font-mono bg-gray-100 px-1 py-0.5 rounded border border-gray-200">[OPTION:Clé]</code> : Quantité exacte de l\'option.</li>
                                                        </ul>
                                                        <strong class="text-primary-600 block mt-4 mb-2">🔀 Affichage Conditionnel</strong>
                                                        <ul class="list-disc pl-5 space-y-1 text-xs">
                                                            <li><code class="font-mono bg-gray-100 px-1 rounded border border-gray-200">[IF_OPTION:clé] texte [/IF_OPTION]</code> : S\'affiche si l\'option est prise.</li>
                                                            <li><code class="font-mono bg-gray-100 px-1 rounded border border-gray-200">[IF_QUANTITY>=3] texte [/IF_QUANTITY]</code> : Opérateurs >=, <=, >, <, ==</li>
                                                            <li><code class="font-mono bg-gray-100 px-1 rounded border border-gray-200">[IF_PAX_CHILDREN>0] texte [/IF_PAX_CHILDREN]</code> : (Marche aussi avec IF_PAX_ADULTS)</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        ')),
                                ])
                        ]),

                ])->columnSpan(['lg' => 2]),

                // -------------------------------------------------------------
                // COLONNE DROITE (Latérale - Largeur 1/3)
                // -------------------------------------------------------------
                Group::make()->schema([
                    
                    Section::make(__('Classification & Fournisseurs'))
                        ->schema([
                            Select::make('category_id')
                                ->relationship('category', 'name')
                                ->label(__('Catégorie de produit'))
                                ->required()
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label(__('Nouvelle catégorie'))
                                        ->required(),
                                ]),

                            Select::make('virtual_supplier_ids')
                                ->label(__('Fournisseurs rattachés'))
                                ->multiple()
                                ->options(fn() => \App\Models\Supplier::pluck('name', 'id'))
                                ->required()
                                ->live()
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Select $component, ?\Illuminate\Database\Eloquent\Model $record) {
                                    if ($record && $record->exists) {
                                        $component->state($record->productSuppliers->pluck('supplier_id')->toArray());
                                    }
                                })
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    $repeaterState = $get('productSuppliers') ?? [];
                                    $selectedIds = $state ?? [];
                                    
                                    foreach ($selectedIds as $sId) {
                                        $exists = false;
                                        foreach ($repeaterState as $item) {
                                            if (($item['supplier_id'] ?? null) == $sId) {
                                                $exists = true; break;
                                            }
                                        }
                                        if (!$exists) {
                                            $repeaterState[(string) Str::uuid()] = [
                                                'supplier_id' => $sId,
                                                'email_subject' => null,
                                                'fax_header' => null,
                                                'email_template' => null,
                                            ];
                                        }
                                    }
                                    
                                    $repeaterState = array_filter($repeaterState, function ($item) use ($selectedIds) {
                                        return in_array($item['supplier_id'] ?? null, $selectedIds);
                                    });
                                    
                                    $set('productSuppliers', $repeaterState);
                                }),
                        ]),

                    Section::make(__('Accès des agences'))
                        ->description(__('Laissez vide pour rendre la prestation accessible à toutes les agences.'))
                        ->schema([
                            CheckboxList::make('clientGroups')
                                ->label(__('Groupes d’agences autorisés'))
                                ->relationship('clientGroups', 'name')
                                ->columns(1)
                                ->helperText(__('Seules les agences appartenant à l’un de ces groupes pourront voir et demander cette prestation.')),
                        ]),

                    Section::make(__('Planning & Fermetures'))
                        ->description(__('Gérez les jours d\'exploitation hebdomadaires et les dates d\'exclusion.'))
                        ->schema([
                            CheckboxList::make('available_days')
                                ->label(__('Jours d\'ouverture de l\'activité'))
                                ->options([
                                    'mon' => __('Lun'),
                                    'tue' => __('Mar'),
                                    'wed' => __('Mer'),
                                    'thu' => __('Jeu'),
                                    'fri' => __('Ven'),
                                    'sat' => __('Sam'),
                                    'sun' => __('Dim'),
                                ])
                                ->columns(4),

                            Repeater::make('blackout_dates')
                                ->label(__('Dates de fermeture exceptionnelle'))
                                ->addActionLabel(__('Bloquer une date spécifique'))
                                ->collapsible()
                                ->collapsed() 
                                ->schema([
                                    DatePicker::make('date')
                                        ->label(__('Date exclue'))
                                        ->required(),
                                ])
                                ->defaultItems(0),
                        ]),

                    Section::make(__('Paramètres de Vente'))
                        ->schema([
                            TextInput::make('max_pax')
                                ->label(__('Capacité maximale (Pax)'))
                                ->placeholder(__('Ex: 10 (Laissez vide si illimité)'))
                                ->helperText(__('Empêchera l\'agence de réserver pour un nombre supérieur à cette limite.'))
                                ->numeric()
                                ->default(null)
                                ->dehydrateStateUsing(fn ($state): ?int => blank($state) ? null : max(1, (int) $state))
                                ->minValue(1),

                            Toggle::make('is_public')
                                ->label(__('Produit public (Vitrine)'))
                                ->helperText(__('Si activé, l\'activité s\'affiche sur le catalogue sans authentification (prix masqué).'))
                                ->default(true),

                            Toggle::make('is_on_demand')
                                ->label(__('Sur devis uniquement'))
                                ->helperText(__('L\'agence devra faire une demande manuelle.')),

                            Toggle::make('is_lottery')
                                ->label(__('Disponibilité limitée'))
                                ->helperText(__('La disponibilité de ce produit est limitée.')),

                            TextInput::make('days_before_opening')
                                ->label(__('Ouverture des ventes (J-)'))
                                ->placeholder(__('Ex: 30'))
                                ->numeric()
                                ->suffix(__('jours')),

                            TextInput::make('days_before_request_deadline')
                                ->label(__('Date limite de demande (J-)'))
                                ->placeholder(__('Ex: 7'))
                                ->numeric()
                                ->minValue(0)
                                ->suffix(__('jours'))
                                ->helperText(__('L’agence ne pourra plus demander cette prestation après ce délai.')),
                        ]),

                    Section::make(__('Politique d\'annulation'))
                        ->schema([
                            Select::make('cancellation_type')
                                ->hiddenLabel() 
                                ->options([
                                    'general' => __('Barème général'),
                                    'specific' => __('Barème spécifique'),
                                ])
                                ->default('general')
                                ->live(),

                            Textarea::make('cancellation_specifics')
                                ->label(__('Détails des frais'))
                                ->placeholder(__('Ex: Non remboursable à partir de J-7...'))
                                ->visible(fn (Get $get) => $get('cancellation_type') === 'specific')
                                ->columnSpanFull(),
                        ]),

                    Section::make(__('Note interne'))
                        ->description(__('Visible uniquement par l’équipe Takada.'))
                        ->schema([
                            Textarea::make('internal_note')
                                ->label(__('Note interne'))
                                ->rows(6)
                                ->placeholder(__('Informations internes, consignes ou points de vigilance…')),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }
}
