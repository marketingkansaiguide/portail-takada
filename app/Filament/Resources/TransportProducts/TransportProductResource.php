<?php

namespace App\Filament\Resources\TransportProducts;

use App\Filament\Resources\TransportProducts\Pages;
use App\Models\Product;
use BackedEnum;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransportProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    public static function getNavigationLabel(): string
    {
        return __('Transports');
    }

    public static function getModelLabel(): string
    {
        return __('Billet de transport');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Transports');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description', 'category.name', 'productSuppliers.supplier.name'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            __('Catégorie') => $record->category?->name,
            __('Fournisseur') => $record->productSuppliers->map(fn ($ps) => $ps->supplier?->name)->filter()->implode(', '),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('product_type', 'transport');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()->schema([
                    Section::make(__('Présentation du Billet / Transport'))
                        ->description(__('Renseignez le nom de la prestation, sa description et ajoutez les visuels.'))
                        ->schema([
                            TextInput::make('name')
                                ->label(__('Nom du Billet / Prestation'))
                                ->placeholder(__('Ex: Billet de train / bus à l\'unité'))
                                ->required()
                                ->columnSpanFull(),

                            Textarea::make('description')
                                ->label(__('Description / Consignes'))
                                ->placeholder(__('Précisions sur les réservations de trains ou bus...'))
                                ->rows(4)
                                ->columnSpanFull(),

                            // Visuels du produit
                            FileUpload::make('images')
                                ->label(__('Photos / Visuels du produit'))
                                ->image()
                                ->multiple()
                                ->reorderable()
                                ->disk('public')
                                ->directory('products')
                                ->saveUploadedFileUsing(fn (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): string => \App\Support\ImageOptimizer::storeUpload($file, 'public', 'products'))
                                ->panelLayout('grid')
                                ->columnSpanFull()
                                ->helperText(__('Vous pouvez ajouter plusieurs images et les réorganiser par glisser-déposer.')),
                        ]),

                    Section::make(__('Classes & Options de transport'))
                        ->description(__('Définissez les classes ou options sélectionnables par l\'agence pour ses trajets (ex: Voiture Standard, Green Car, Gran Class...).'))
                        ->schema([
                            Repeater::make('productOptions')
                                ->relationship()
                                ->hiddenLabel()
                                ->addActionLabel(__('Ajouter une option / classe'))
                                ->itemLabel(fn (array $state): ?string => isset($state['name']) ? $state['name'] . ' (+' . ($state['price_modifier'] ?? 0) . ' ¥)' : __('Nouvelle classe'))
                                ->collapsible()
                                ->columns(12)
                                ->schema([
                                    TextInput::make('group_name')
                                        ->label(__('Groupe (Ex: Classe)'))
                                        ->placeholder(__('Laissez vide si optionnel'))
                                        ->columnSpan(['default' => 12, 'md' => 4]),

                                    TextInput::make('name')
                                        ->label(__('Nom de l\'Option'))
                                        ->placeholder(__('Ex: Green Car'))
                                        ->required()
                                        ->columnSpan(['default' => 12, 'md' => 4]),

                                    TextInput::make('price_modifier')
                                        ->label(__('Supplément (¥)'))
                                        ->numeric()
                                        ->default(0)
                                        ->required()
                                        ->columnSpan(['default' => 12, 'md' => 4]),

                                    Select::make('billing_type')
                                        ->label(__('Facturation'))
                                        ->options([
                                            'per_pax' => __('Par Pax'),
                                            'per_booking' => __('Fixe par dossier'),
                                        ])
                                        ->default('per_pax')
                                        ->required()
                                        ->columnSpan(['default' => 12, 'md' => 6]),

                                    Toggle::make('is_required')
                                        ->label(__('Obligatoire'))
                                        ->default(false)
                                        ->inline(false)
                                        ->columnSpan(['default' => 12, 'md' => 6]),
                                ])
                        ]),

                    Section::make(__('Frais de Réservation / Émission'))
                        ->description(__('Grille tarifaire des frais de service / émission Takada.'))
                        ->schema([
                            Repeater::make('productPeriods')
                                ->relationship()
                                ->hiddenLabel()
                                ->collapsible()
                                ->addActionLabel(__('Définir une période de tarif'))
                                ->schema([
                                    TextInput::make('name')
                                        ->label(__('Nom de la saison'))
                                        ->default('Toute l\'année')
                                        ->required(),

                                    Repeater::make('date_ranges')
                                        ->label(__('Plages de validité (facultatif pour toute l’année)'))
                                        ->helperText(__('Une même saison peut couvrir plusieurs plages de dates.'))
                                        ->addActionLabel(__('Ajouter une plage de dates'))
                                        ->schema([
                                            TextInput::make('start_date')
                                                ->label(__('Date de début (JJ/MM)'))
                                                ->mask('99/99')
                                                ->formatStateUsing(fn (?string $state) => $state && str_contains($state, '-') ? implode('/', array_reverse(explode('-', $state))) : $state)
                                                ->dehydrateStateUsing(fn (?string $state) => $state && str_contains($state, '/') ? implode('-', array_reverse(explode('/', $state))) : $state)
                                                ->required(),
                                            TextInput::make('end_date')
                                                ->label(__('Date de fin (JJ/MM)'))
                                                ->mask('99/99')
                                                ->formatStateUsing(fn (?string $state) => $state && str_contains($state, '-') ? implode('/', array_reverse(explode('-', $state))) : $state)
                                                ->dehydrateStateUsing(fn (?string $state) => $state && str_contains($state, '/') ? implode('-', array_reverse(explode('/', $state))) : $state)
                                                ->required(),
                                        ])->columns(2),

                                    Repeater::make('productPrices')
                                        ->relationship()
                                        ->label(__('Frais fixes par tranche de pax'))
                                        ->schema([
                                            TextInput::make('min_pax')->label(__('Pax Min'))->numeric()->default(1),
                                            TextInput::make('max_pax')->label(__('Pax Max'))->numeric()->default(99),
                                            TextInput::make('price')
                                                ->label(__('Frais de dossier / émission (¥)'))
                                                ->numeric()
                                                ->default(0)
                                                ->required(),
                                        ])->columns(3)
                                ])
                        ]),
                ])->columnSpan(['lg' => 2]),

                Group::make()->schema([
                    Section::make(__('Configuration Métier'))
                        ->schema([
                            Select::make('category_id')
                                ->relationship('category', 'name')
                                ->label(__('Catégorie'))
                                ->required()
                                ->searchable()
                                ->preload(),

                            Toggle::make('is_on_demand')
                                ->label(__('Toujours sur devis'))
                                ->default(true)
                                ->disabled()
                                ->dehydrated(),

                            Toggle::make('is_public')
                                ->label(__('Produit actif'))
                                ->default(true),

                            CheckboxList::make('clientGroups')
                                ->label(__('Groupes d’agences autorisés'))
                                ->relationship('clientGroups', 'name')
                                ->columns(1)
                                ->helperText(__('Laissez vide pour permettre à toutes les agences de demander ce transport.')),

                            TextInput::make('days_before_opening')
                                ->label(__('Ouverture des ventes (J-)'))
                                ->placeholder(__('Ex: 30'))
                                ->numeric()
                                ->minValue(0)
                                ->suffix(__('jours')),

                            TextInput::make('days_before_request_deadline')
                                ->label(__('Date limite de demande (J-)'))
                                ->placeholder(__('Ex: 7'))
                                ->numeric()
                                ->minValue(0)
                                ->suffix(__('jours'))
                                ->helperText(__('L’agence ne pourra plus demander cette prestation après ce délai.')),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('primary_image_url')
                    ->label(__('Visuel'))
                    ->circular()
                    ->imageSize(48),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Billet / Transport'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('Catégorie'))
                    ->badge(),

                Tables\Columns\IconColumn::make('is_on_demand')
                    ->label(__('Sur Devis'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Créé le'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportProducts::route('/'),
            'create' => Pages\CreateTransportProduct::route('/create'),
            'edit' => Pages\EditTransportProduct::route('/{record}/edit'),
        ];
    }
}
