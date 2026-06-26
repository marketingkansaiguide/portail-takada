<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
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
                
                // 🏢 COLONNE PRINCIPALE (A GAUCHE : 2/3 de l'écran)
                Group::make()->schema([
                    Section::make('Présentation de la prestation')
                        ->description('Renseignez le titre, la description détaillée et ajoutez les visuels.')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nom du produit / Prestation')
                                ->placeholder('Ex: Billet Shinkansen Kyoto ou Visite Ghibli')
                                ->required()
                                ->columnSpanFull(),

                            Textarea::make('description')
                                ->label('Description commerciale')
                                ->placeholder('Décrivez précisément l\'activité ou les spécificités du produit...')
                                ->rows(5)
                                ->columnSpanFull(),

                            FileUpload::make('images')
                                ->label('Galerie de photos d\'illustration')
                                ->multiple()
                                ->image()
                                ->reorderable()
                                ->directory('products')
                                ->columnSpanFull(),
                        ]),

                    Section::make('Calendrier & Grilles Tarifaires (Prix NETS)')
                        ->description('Définissez vos saisons de validité, puis ajoutez les grilles dynamiques à l\'intérieur.')
                        ->schema([
                            Repeater::make('productPeriods')
                                ->relationship()
                                ->label('')
                                ->collapsible()
                                ->cloneable()
                                ->addActionLabel('Créer une nouvelle période / saison')
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Nouvelle Saison')
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Nom de la saison (ex: Haute saison)')
                                        ->placeholder('Ex: Golden Week')
                                        ->columnSpanFull()
                                        ->required(),
                                        
                                    Group::make()->schema([
                                        DatePicker::make('start_date')->label('Date de début')->required(),
                                        DatePicker::make('end_date')->label('Date de fin')->required(),
                                    ])->columns(2),
                                    
                                    Repeater::make('productPrices')
                                        ->relationship()
                                        ->label('Grille tarifaire (Pax & Âges)')
                                        ->collapsible()
                                        ->addActionLabel('Ajouter un palier de prix')
                                        ->itemLabel(fn (array $state): ?string => isset($state['price']) ? 'Tarif : ' . $state['price'] . ' ¥' : 'Nouveau tarif')
                                        ->schema([
                                            Group::make()->schema([
                                                TextInput::make('min_pax')->label('Pax Min')->numeric()->default(1),
                                                TextInput::make('max_pax')->label('Pax Max')->numeric()->default(99),
                                            ])->columns(2),
                                            
                                            Group::make()->schema([
                                                TextInput::make('min_age')->label('Âge Min')->numeric()->default(0),
                                                TextInput::make('max_age')->label('Âge Max')->numeric()->default(99),
                                            ])->columns(2),

                                            TextInput::make('price')
                                                ->label('Prix net (¥)')
                                                ->placeholder('0')
                                                ->numeric()
                                                ->required(),
                                        ])->columns(3)
                                ])
                        ])
                ])->columnSpan(['lg' => 2]),

                // 🛠️ BARRE LATÉRALE (A DROITE : 1/3 de l'écran)
                Group::make()->schema([
                    Section::make('Classification')
                        ->schema([
                            Select::make('category_id')
                                ->relationship('category', 'name')
                                ->label('Catégorie de produit')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Nouvelle catégorie')
                                        ->required(),
                                ]),

                            Select::make('supplier_id')
                                ->relationship('supplier', 'name')
                                ->label('Fournisseur rattaché')
                                ->required()
                                ->searchable()
                                ->preload(),
                        ]),

                    Section::make('Paramètres de Vente')
                        ->schema([
                            Toggle::make('is_on_demand')
                                ->label('Sur devis uniquement')
                                ->helperText('L\'agence devra faire une demande manuelle.'),

                            Toggle::make('is_lottery')
                                ->label('Soumis à loterie')
                                ->helperText('Pour les produits à places très limitées.'),

                            TextInput::make('days_before_opening')
                                ->label('Ouverture des ventes (J-)')
                                ->placeholder('Ex: 30')
                                ->numeric()
                                ->suffix('jours'),
                        ]),

                    Section::make('Politique d\'annulation')
                        ->schema([
                            Select::make('cancellation_type')
                                ->label('Réglementation')
                                ->options([
                                    'general' => 'Barème général',
                                    'specific' => 'Barème spécifique',
                                ])
                                ->default('general')
                                ->live(),

                            Textarea::make('cancellation_specifics')
                                ->label('Détails des frais')
                                ->placeholder('Ex: Non remboursable à partir de J-7...')
                                ->visible(fn ($get) => $get('cancellation_type') === 'specific')
                                ->columnSpanFull(),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }
}