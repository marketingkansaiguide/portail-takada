<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                
                // 🏢 COLONNE PRINCIPALE (À GAUCHE : 2/3 de l'écran)
                Group::make()->schema([
                    Section::make(__('Présentation de la prestation'))
                        ->description(__('Renseignez le titre, la description détaillée et ajoutez les visuels.'))
                        ->schema([
                            TextInput::make('name')
                                ->label(__('Nom du produit / Prestation'))
                                ->placeholder(__('Ex: Location de Kimono à Kyoto / Billet Shinkansen'))
                                ->required()
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
                                ->directory('products')
                                ->columnSpanFull(),
                        ]),

                    Section::make(__('Informations requises lors de l\'achat'))
                        ->description(__('Configurez les questions spécifiques que l\'agence devra remplir pour valider la réservation.'))
                        ->schema([
                            Repeater::make('custom_field_definitions')
                                ->label('')
                                ->addActionLabel(__('Demander une information spécifique'))
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? __('Nouveau champ requis'))
                                ->collapsible()
                                ->schema([
                                    Group::make()->schema([
                                        TextInput::make('name')
                                            ->label(__('Nom du champ (Ce qui sera demandé)'))
                                            ->placeholder(__('Ex: Taille en cm, Numéro de passeport...'))
                                            ->required(),

                                        Select::make('type')
                                            ->label(__('Format de la réponse'))
                                            ->options([
                                                'text' => __('Texte court'),
                                                'textarea' => __('Texte long'),
                                                'number' => __('Nombre entier'),
                                                'date' => __('Date'),
                                                'toggle' => __('Case à cocher (Oui/Non)'),
                                            ])
                                            ->required(),
                                    ])->columns(2),

                                    Group::make()->schema([
                                        TextInput::make('placeholder')
                                            ->label(__('Exemple d\'aide (Placeholder)'))
                                            ->placeholder(__('Ex: M, L, XL ou 175cm...')),

                                        Toggle::make('is_required')
                                            ->label(__('Rendre obligatoire'))
                                            ->default(true)
                                            ->inline(false),
                                    ])->columns(2),

                                    Toggle::make('is_per_passenger')
                                        ->label(__('Multiplier cette question par le nombre de voyageurs / quantité'))
                                        ->helperText(__('Si coché, et que l\'agence achète 4 unités, le système générera automatiquement 4 lignes de saisie distinctes en Front-Office.'))
                                        ->default(false)
                                        ->columnSpanFull(),
                                ])
                        ]),

                    Section::make(__('Options & Déclinaisons tarifaires'))
                        ->description(__('Ajoutez des variantes ou des services optionnels payants applicables à ce produit.'))
                        ->schema([
                            Repeater::make('productOptions')
                                ->relationship()
                                ->label('')
                                ->addActionLabel(__('Ajouter une option / déclinaison'))
                                ->itemLabel(fn (array $state): ?string => isset($state['name']) ? $state['name'] . ' (+' . ($state['price_modifier'] ?? 0) . ' ¥)' : __('Nouvelle option'))
                                ->collapsible()
                                ->schema([
                                    Group::make()->schema([
                                        TextInput::make('name')
                                            ->label(__('Nom de l\'option / variante'))
                                            ->placeholder(__('Ex: Tissu Soie Premium, Option Guide Privé, Classe Supérieure'))
                                            ->required(),

                                        TextInput::make('price_modifier')
                                            ->label(__('Supplément Prix Net (¥)'))
                                            ->numeric()
                                            ->default(0)
                                            ->required()
                                            ->placeholder('0'),
                                    ])->columns(2),

                                    Select::make('billing_type')
                                        ->label(__('Mode d\'application du supplément tarifaire'))
                                        ->options([
                                            'per_pax' => __('Par voyageur (Multiplié par le nombre de pax/quantité)'),
                                            'per_booking' => __('Frais fixes (Appliqué une seule fois pour tout le dossier)'),
                                        ])
                                        ->default('per_pax')
                                        ->required(),
                                ])
                        ]),

                    Section::make(__('Calendrier & Grilles Tarifaires (Prix NETS)'))
                        ->description(__('Définissez vos saisons de validité, puis ajoutez les grilles dynamiques à l\'intérieur.'))
                        ->schema([
                            Repeater::make('productPeriods')
                                ->relationship()
                                ->label('')
                                ->collapsible()
                                ->cloneable()
                                ->addActionLabel(__('Créer une nouvelle période / saison'))
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? __('Nouvelle Saison'))
                                ->schema([
                                    TextInput::make('name')
                                        ->label(__('Nom de la saison (ex: Haute saison)'))
                                        ->placeholder(__('Ex: Golden Week'))
                                        ->columnSpanFull()
                                        ->required(),
                                        
                                    Group::make()->schema([
                                        DatePicker::make('start_date')->label(__('Date de début'))->required(),
                                        DatePicker::make('end_date')->label(__('Date de fin'))->required(),
                                    ])->columns(2),
                                    
                                    Repeater::make('productPrices')
                                        ->relationship()
                                        ->label(__('Grille tarifaire (Pax & Âges)'))
                                        ->collapsible()
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
                                                ->label(__('Prix net (¥)'))
                                                ->placeholder('0')
                                                ->numeric()
                                                ->required(),
                                        ])->columns(3)
                                ])
                        ])
                ])->columnSpan(['lg' => 2]),

                // 🛠️ BARRE LATÉRALE (À DROITE : 1/3 de l'écran)
                Group::make()->schema([
                    Section::make(__('Classification'))
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

                            Select::make('supplier_id')
                                ->relationship('supplier', 'name')
                                ->label(__('Fournisseur rattaché'))
                                ->required()
                                ->searchable()
                                ->preload(),
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
                                ->schema([
                                    DatePicker::make('date')
                                        ->label(__('Date exclue'))
                                        ->required(),
                                ])
                                ->collapsible()
                                ->defaultItems(0),
                        ]),

                    Section::make(__('Paramètres de Vente'))
                        ->schema([
                            Toggle::make('is_on_demand')
                                ->label(__('Sur devis uniquement'))
                                ->helperText(__('L\'agence devra faire une demande manuelle.')),

                            Toggle::make('is_lottery')
                                ->label(__('Soumis à loterie'))
                                ->helperText(__('Pour les produits à places très limitées.')),

                            TextInput::make('days_before_opening')
                                ->label(__('Ouverture des ventes (J-)'))
                                ->placeholder(__('Ex: 30'))
                                ->numeric()
                                ->suffix(__('jours')),
                        ]),

                    Section::make(__('Politique d\'annulation'))
                        ->schema([
                            Select::make('cancellation_type')
                                ->label(__('Réglementation'))
                                ->options([
                                    'general' => __('Barème général'),
                                    'specific' => __('Barème spécifique'),
                                ])
                                ->default('general')
                                ->live(),

                            Textarea::make('cancellation_specifics')
                                ->label(__('Détails des frais'))
                                ->placeholder(__('Ex: Non remboursable à partir de J-7...'))
                                ->visible(fn ($get) => $get('cancellation_type') === 'specific')
                                ->columnSpanFull(),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }
}