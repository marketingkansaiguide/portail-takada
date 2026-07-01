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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
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
                                    // 💡 PASSAGE À 3 COLONNES POUR INTÉGRER LA CLÉ
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
                                            ])
                                            ->live()
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
                                            ->placeholder(__('Ex: Tissu Soie Premium...'))
                                            ->required(),

                                        TextInput::make('code')
                                            ->label(__('Clé (pour shortcode email)'))
                                            ->placeholder(__('Ex: dressing, guide...'))
                                            ->nullable(),

                                        TextInput::make('price_modifier')
                                            ->label(__('Supplément Prix Net (¥)'))
                                            ->numeric()
                                            ->default(0)
                                            ->required()
                                            ->placeholder('0'),
                                    ])->columns(3),

                                    Select::make('billing_type')
                                        ->label(__('Mode d\'application du supplément tarifaire'))
                                        ->options([
                                            'per_pax' => __('Par voyageur (Multiplié par le nombre de pax/quantité)'),
                                            'per_booking' => __('Frais fixes (Appliqué une seule fois pour tout le dossier)'),
                                            'manual' => __('Quantité au choix (Saisie manuelle dans le dossier)'),
                                        ])
                                        ->default('per_pax')
                                        ->required(),
                                ])
                        ]),

                    Section::make(__('Calendrier & Grilles Tarifaires (Prix NETS)'))
                        ->description(__('Définissez vos saisons de validité, l\'âge limite des enfants et vos prix.'))
                        ->schema([
                            TextInput::make('child_age_limit')
                                ->label(__('Âge maximum pour être considéré enfant (Inclus)'))
                                ->helperText(__('Ex: Si 11, un enfant de 11 ans (au jour de l\'activité) comptera comme enfant. À 12 ans, il comptera comme adulte.'))
                                ->numeric()
                                ->default(11)
                                ->required(),

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
                        ]),

                    Section::make(__('Modèle d\'E-mail pour le Fournisseur'))
                        ->description(__('Rédigez l\'objet et le texte par défaut qui seront générés dans le dossier client.'))
                        ->schema([
                            TextInput::make('supplier_email_subject')
                                ->label(__('Objet de l\'e-mail'))
                                ->placeholder("Ex: ご予約依頼 : [DOSSIER_REF] / [LEAD_NAME]")
                                ->columnSpanFull(),

                            Textarea::make('supplier_email_template')
                                ->label(__('Corps du message'))
                                ->placeholder("Bonjour [CONTACT_FOURNISSEUR],\n\nJe souhaite réserver la prestation suivante...\n\n[IF_QUANTITY>=10]Attention c'est un grand groupe ![/IF_QUANTITY]\n\n[IF_PAX_CHILDREN>0]Parmi eux, il y a [PAX_CHILDREN] enfants ![/IF_PAX_CHILDREN]\n\n[IF_OPTION:dressing]Options incluses : Habillage pour [OPTION:dressing] personnes.[/IF_OPTION]\n\nCordialement,\n[NOM_AGENT]")
                                ->rows(10)
                                ->columnSpanFull(),

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
                                ->visible(fn (Get $get) => $get('cancellation_type') === 'specific')
                                ->columnSpanFull(),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }
}