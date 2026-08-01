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
                                            ->placeholder(__('Ex: M, L, XL ou 175cm...'))
                                            ->visible(fn (Get $get) => in_array($get('type'), ['text', 'textarea', 'number', 'select'])),

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

                                    TextInput::make('price_modifier')
                                        ->label(__('Supplément (¥)'))
                                        ->numeric()
                                        ->default(0)
                                        ->required()
                                        ->placeholder('0')
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
                                ])
                        ]),

                    Section::make(__('Calendrier & Grilles Tarifaires (Prix NETS)'))
                        ->description(__('Définissez vos saisons annuelles de validité, l\'âge limite des enfants et vos prix.'))
                        ->schema([
                            TextInput::make('child_age_limit')
                                ->label(__('Âge maximum pour être considéré enfant (Inclus)'))
                                ->helperText(__('Ex: Si 11, un enfant de 11 ans (au jour de l\'activité) comptera comme enfant. À 12 ans, il comptera comme adulte.'))
                                ->numeric()
                                ->default(11)
                                ->required(),

                            Repeater::make('productPeriods')
                                ->relationship()
                                ->hiddenLabel() 
                                ->collapsible()
                                ->collapsed()
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
                                        TextInput::make('start_date')
                                            ->label(__('Date de début (JJ/MM)'))
                                            ->placeholder('Ex: 01/04')
                                            ->mask('99/99')
                                            ->rules([
                                                function () {
                                                    return function (string $attribute, $value, \Closure $fail) {
                                                        $parts = explode('/', $value);
                                                        if (count($parts) !== 2 || !checkdate((int)($parts[1] ?? 0), (int)($parts[0] ?? 0), 2024)) {
                                                            $fail('Date de début invalide (Format JJ/MM attendu).');
                                                        }
                                                    };
                                                },
                                            ])
                                            ->formatStateUsing(function (?string $state) {
                                                if (!$state) return null;
                                                $parts = explode('-', $state);
                                                return count($parts) === 2 ? $parts[1] . '/' . $parts[0] : $state;
                                            })
                                            ->dehydrateStateUsing(function (?string $state) {
                                                if (!$state) return null;
                                                $parts = explode('/', $state);
                                                return count($parts) === 2 ? $parts[1] . '-' . $parts[0] : $state;
                                            })
                                            ->required(),

                                        TextInput::make('end_date')
                                            ->label(__('Date de fin (JJ/MM)'))
                                            ->placeholder('Ex: 31/10')
                                            ->mask('99/99')
                                            ->rules([
                                                function () {
                                                    return function (string $attribute, $value, \Closure $fail) {
                                                        $parts = explode('/', $value);
                                                        if (count($parts) !== 2 || !checkdate((int)($parts[1] ?? 0), (int)($parts[0] ?? 0), 2024)) {
                                                            $fail('Date de fin invalide (Format JJ/MM attendu).');
                                                        }
                                                    };
                                                },
                                            ])
                                            ->formatStateUsing(function (?string $state) {
                                                if (!$state) return null;
                                                $parts = explode('-', $state);
                                                return count($parts) === 2 ? $parts[1] . '/' . $parts[0] : $state;
                                            })
                                            ->dehydrateStateUsing(function (?string $state) {
                                                if (!$state) return null;
                                                $parts = explode('/', $state);
                                                return count($parts) === 2 ? $parts[1] . '-' . $parts[0] : $state;
                                            })
                                            ->required(),
                                    ])->columns(2),
                                    
                                    Repeater::make('productPrices')
                                        ->relationship()
                                        ->label(__('Grille tarifaire (Pax & Âges)'))
                                        ->collapsible()
                                        ->collapsed()
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
                                            Section::make('En-tête visuelle du document FAX')
                                                ->collapsed() 
                                                ->columns(2)
                                                ->schema([
                                                    Group::make()->schema([
                                                        Placeholder::make('lbl_to')
                                                            ->hiddenLabel()
                                                            ->content(new \Illuminate\Support\HtmlString('<div style="font-weight:bold; font-size:1.1rem; border-bottom: 2px solid #e5e7eb; margin-bottom: 0.5rem; padding-bottom: 0.2rem;">送付先： (Destinataire)</div>')),
                                                        TextInput::make('to_company_name')
                                                            ->label('Nom de l\'entreprise')
                                                            ->placeholder('Ex: 旭川合同自動車㈱ タクシーコールセンター'),
                                                        TextInput::make('to_contact_name')
                                                            ->label('Nom du contact')
                                                            ->default('ご担当者様')
                                                            ->placeholder('Ex: 田中様'),
                                                        TextInput::make('to_tel')
                                                            ->label('TEL Destinataire')
                                                            ->placeholder('Ex: 0166-33-3131'),
                                                        TextInput::make('to_fax')
                                                            ->label('FAX Destinataire')
                                                            ->placeholder('Ex: 0166-34-0930'),
                                                    ])->columnSpan(1),

                                                    Group::make()->schema([
                                                        Placeholder::make('lbl_from')
                                                            ->hiddenLabel()
                                                            ->content(new \Illuminate\Support\HtmlString('<div style="font-weight:bold; font-size:1.1rem; border-bottom: 2px solid #e5e7eb; margin-bottom: 0.5rem; padding-bottom: 0.2rem;">発信元： (Expéditeur)</div>')),
                                                        TextInput::make('from_company')
                                                            ->label('Société émettrice')
                                                            ->default('TAKADA TRAVEL合同会社')
                                                            ->placeholder('Ex: TAKADA TRAVEL...'),
                                                        Textarea::make('from_address')
                                                            ->label('Adresse postale')
                                                            ->default("〒532-0012大阪市淀川区木川東\n3丁目1-23 KC新大阪ビル 2階")
                                                            ->rows(2)
                                                            ->placeholder("〒532-0012..."),
                                                        TextInput::make('from_contact')
                                                            ->label('Contact expéditeur')
                                                            ->default('担当者： [NOM_AGENT]')
                                                            ->helperText('Laissez [NOM_AGENT] pour utiliser le nom du compte connecté.')
                                                            ->placeholder('担当者： [NOM_AGENT]'),
                                                        TextInput::make('from_mail')
                                                            ->label('Email de réponse')
                                                            ->default('MAIL : [EMAIL_AGENT]')
                                                            ->helperText('Laissez [EMAIL_AGENT] pour utiliser l\'email du compte connecté.')
                                                            ->placeholder('MAIL : [EMAIL_AGENT]'),
                                                        TextInput::make('from_tel')
                                                            ->label('TEL émetteur')
                                                            ->default('TEL：06-6195-9799')
                                                            ->placeholder('TEL：06-6195-9799'),
                                                        TextInput::make('from_fax')
                                                            ->label('FAX émetteur')
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
                                ->minValue(1),

                            Toggle::make('is_public')
                                ->label(__('Produit public (Vitrine)'))
                                ->helperText(__('Si activé, l\'activité s\'affiche sur le catalogue sans authentification (prix masqué).'))
                                ->default(true),

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
                ])->columnSpan(['lg' => 1]),
            ]);
    }
}