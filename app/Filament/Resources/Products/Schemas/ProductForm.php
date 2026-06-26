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
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // --- SECTION 1 : INFOS DE BASE ---
            Section::make('Informations de base')->schema([
                Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label('Fournisseur')
                    ->required()
                    ->searchable()
                    ->preload(),

                TextInput::make('name')
                    ->label('Nom du produit/prestation')
                    ->required(),

                Select::make('type')
                    ->label('Type de produit')
                    ->options([
                        'train' => 'Billet de Train',
                        'activity' => 'Activité / Visite',
                        'wifi' => 'Pocket WiFi',
                        'other' => 'Autre',
                    ])
                    ->required(),
            ])->columns(3),

            // --- SECTION 2 : VISUELS ET TEXTES ---
            Section::make('Détails & Médias')->schema([
                FileUpload::make('images')
                    ->label('Galerie de photos')
                    ->multiple() // Autorise l'upload de plusieurs images d'un coup !
                    ->image()
                    ->directory('products')
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->label('Description complète (affichée en front-office)')
                    ->columnSpanFull(),
            ]),

            // --- SECTION 3 : RÈGLES ---
            Section::make('Règles & Conditions')->schema([
                Select::make('cancellation_type')
                    ->label('Conditions d\'annulation')
                    ->options([
                        'general' => 'Appliquer le barème Général',
                        'specific' => 'Créer un barème Spécifique',
                    ])
                    ->default('general')
                    ->live(), // "live()" permet de réagir au clic pour afficher le champ suivant !

                Textarea::make('cancellation_specifics')
                    ->label('Détails du barème spécifique')
                    ->visible(fn (\Filament\Forms\Get $get) => $get('cancellation_type') === 'specific')
                    ->columnSpanFull(),

                Toggle::make('is_lottery')
                    ->label('Soumis à loterie (Ex: Musée Ghibli)'),

                Toggle::make('is_on_demand')
                    ->label('Sur devis uniquement'),

                TextInput::make('days_before_opening')
                    ->label('Ouverture des réservations (J-)')
                    ->numeric()
                    ->suffix('jours'),
            ])->columns(2),

            // --- SECTION 4 : LE CALENDRIER ET LES PRIX DYNAMIQUES ---
            Section::make('Calendrier et Grilles Tarifaires (Prix NETS)')
                ->description('1. Créez vos saisons/périodes. 2. Ajoutez vos prix par Pax/Âge dans chaque saison.')
                ->schema([
                    // Le 1er Repeater : Les Périodes
                    Repeater::make('productPeriods')
                        ->relationship() // Lie tout seul à la table product_periods
                        ->label('Saisons / Périodes de validité')
                        ->schema([
                            TextInput::make('name')->label('Nom de la saison (ex: Haute saison)')->required(),
                            DatePicker::make('start_date')->label('Date de début')->required(),
                            DatePicker::make('end_date')->label('Date de fin')->required(),
                            
                            // Le 2ème Repeater (Imbriqué) : Les Prix
                            Repeater::make('productPrices')
                                ->relationship() // Lie tout seul à la table product_prices
                                ->label('Grille des tarifs pour cette saison')
                                ->schema([
                                    TextInput::make('min_pax')->label('Pax Min')->numeric()->default(1)->required(),
                                    TextInput::make('max_pax')->label('Pax Max')->numeric()->default(99)->required(),
                                    TextInput::make('min_age')->label('Âge Min')->numeric()->default(0)->required(),
                                    TextInput::make('max_age')->label('Âge Max')->numeric()->default(99)->required(),
                                    TextInput::make('price')->label('Prix unitaire (¥)')->numeric()->required(),
                                ])->columns(5)
                                ->columnSpanFull()
                                ->addActionLabel('Ajouter une ligne de prix')
                        ])->columns(3)
                        ->addActionLabel('Ajouter une saison / période')
                ])
        ]);
    }
}