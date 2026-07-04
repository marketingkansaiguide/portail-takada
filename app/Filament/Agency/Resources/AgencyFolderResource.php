<?php

namespace App\Filament\Agency\Resources;

use App\Filament\Agency\Resources\AgencyFolderResource\Pages;
use App\Models\Folder;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Filament\Schemas\Schema; 
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\EditAction; // 💡 L'import correct et unifié pour votre version de Filament !
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Illuminate\Database\Eloquent\Builder;

class AgencyFolderResource extends Resource
{
    protected static ?string $model = Folder::class;
    
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';
    
    public static function getNavigationLabel(): string
    {
        return __('Mes Dossiers');
    }

    public static function getModelLabel(): string
    {
        return __('Dossier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Mes Dossiers');
    }

    public static function getEloquentQuery(): Builder
    {
        // SÉCURITÉ : L'agence ne voit que ses propres dossiers
        return parent::getEloquentQuery()->where('agency_id', auth()->user()->agency_id);
    }

    // Réutilisation des fonctions de calcul parfaites de l'Admin
    public static function updatePassengerCount($set, $get) {
        \App\Filament\Resources\Folders\FolderResource::updatePassengerCount($set, $get);
    }
    public static function updateItemPrices($set, $get) {
        \App\Filament\Resources\Folders\FolderResource::updateItemPrices($set, $get);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Group::make()->schema([
                Section::make('Informations Principales')
                    ->description('Renseignez les informations de base du groupe ou du voyageur.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('folder_name')
                            ->label('Nom du dossier / Réf. Groupe')
                            ->placeholder('Ex: Groupe Dupont')
                            ->required(),

                        TextInput::make('lead_traveler_name')
                            ->label('Nom du voyageur principal')
                            ->required(),

                        DatePicker::make('start_date')
                            ->label('Date d\'arrivée au Japon')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get)),

                        DatePicker::make('end_date')
                            ->label('Date de départ')
                            ->required()
                            ->live()
                            ->minDate(fn ($get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null),
                    ]),

                Section::make('Liste des Voyageurs')
                    ->description('Détaillez les participants pour calculer les tarifs (enfants/adultes) et alerter sur les allergies.')
                    ->schema([
                        Repeater::make('folderPassengers')
                            ->relationship()
                            ->label('')
                            ->addActionLabel('Ajouter un voyageur')
                            ->collapsible()
                            ->live()
                            ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get))
                            ->itemLabel(fn (array $state) => trim(($state['first_name'] ?? '') . ' ' . ($state['last_name'] ?? '')) ?: 'Nouveau voyageur')
                            ->schema([
                                Group::make()->schema([
                                    TextInput::make('last_name')->label('Nom')->required(),
                                    TextInput::make('first_name')->label('Prénom')->required(),
                                    DatePicker::make('birth_date')
                                        ->label('Date de naissance')
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get)),
                                    TextInput::make('nationality')->label('Nationalité')->required(),
                                ])->columns(4),
                                Textarea::make('dietary_restrictions')->label('Allergies')->rows(1),
                                Textarea::make('mobility_concerns')->label('Besoins PMR')->rows(1),
                            ])
                    ]),

                Section::make('Prestations souhaitées')
                    ->description('Ajoutez des prestations depuis notre catalogue.')
                    ->schema([
                        Repeater::make('folderItems')
                            ->relationship()
                            ->label('')
                            ->addActionLabel('Ajouter une prestation au dossier')
                            ->collapsible()
                            ->live()
                            ->itemLabel(function (array $state) {
                                if (!isset($state['product_id'])) return 'Nouvelle demande';
                                $productName = \App\Models\Product::find($state['product_id'])?->name ?? 'Produit inconnu';
                                $date = !empty($state['service_date']) ? Carbon::parse($state['service_date'])->format('d/m/Y') : '---';
                                return $productName . ' - ' . $date;
                            })
                            ->schema([
                                Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->label('Produit / Activité')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($set, $get, $state, $old) {
                                        if ($state !== $old) {
                                            $set('selected_options', []);
                                            $set('custom_values', []);
                                        }
                                        self::updateItemPrices($set, $get);
                                    }),
                                    
                                Group::make()->schema([
                                    DatePicker::make('service_date')
                                        ->label('Date souhaitée')
                                        ->required()
                                        ->live()
                                        ->minDate(fn ($get) => $get('../../start_date') ? Carbon::parse($get('../../start_date'))->startOfDay() : null)
                                        ->maxDate(fn ($get) => $get('../../end_date') ? Carbon::parse($get('../../end_date'))->endOfDay() : null)
                                        ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get)),

                                    TextInput::make('quantity')
                                        ->label('Participants')
                                        ->numeric()
                                        ->default(1)
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get)),

                                    Placeholder::make('price_preview')
                                        ->label('Sous-total net estimé')
                                        ->content(fn ($get) => number_format((float)($get('total_price') ?? 0), 0, '.', ' ') . ' ¥'),

                                    Hidden::make('unit_price')->default(0),
                                    Hidden::make('total_price')->default(0),
                                    Hidden::make('item_status_id')->default(1), 
                                ])->columns(3),

                                Repeater::make('selected_options')
                                    ->label('Options')
                                    ->addActionLabel('Ajouter une option')
                                    ->live()
                                    ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get))
                                    ->schema([
                                        Select::make('product_option_id')
                                            ->label('Option')
                                            ->options(function ($get) {
                                                $productId = $get('../../product_id');
                                                if (!$productId) return [];
                                                return \App\Models\ProductOption::where('product_id', $productId)->pluck('name', 'id');
                                            })
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                $set('product_option_id', $state);
                                                $parentSet = function($k, $v) use ($set) { $set('../../'.$k, $v); };
                                                $parentGet = function($k) use ($get) { return $get('../../'.$k); };
                                                self::updateItemPrices($parentSet, $parentGet);
                                            }),
                                        TextInput::make('quantity')
                                            ->label('Qté')
                                            ->numeric()
                                            ->default(1)
                                            ->live()
                                            ->visible(fn ($get) => \App\Models\ProductOption::find($get('product_option_id'))?->billing_type === 'manual')
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                $set('quantity', $state);
                                                $parentSet = function($k, $v) use ($set) { $set('../../'.$k, $v); };
                                                $parentGet = function($k) use ($get) { return $get('../../'.$k); };
                                                self::updateItemPrices($parentSet, $parentGet);
                                            }),
                                    ])->columns(2),

                                Group::make()
                                    ->statePath('custom_values')
                                    ->schema(function ($get) {
                                        $productId = $get('product_id');
                                        if (!$productId) return [];
                                        $product = \App\Models\Product::find($productId);
                                        if (!$product || empty($product->custom_field_definitions)) return [];

                                        $fields = [];
                                        foreach ($product->custom_field_definitions as $def) {
                                            $type = $def['type'] ?? 'text';
                                            $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                                            $label = $def['name'] ?? 'Information';
                                            if ($def['is_per_passenger'] ?? false) $label .= ' (Par passager)';

                                            $field = match ($type) {
                                                'textarea' => Textarea::make($key)->label($label)->rows(2),
                                                'number' => TextInput::make($key)->numeric()->label($label),
                                                'date' => DatePicker::make($key)->label($label),
                                                'toggle' => Toggle::make($key)->label($label)->inline(false),
                                                'select' => Select::make($key)->label($label)->options(array_combine($def['choices'] ?? [], $def['choices'] ?? [])),
                                                default => TextInput::make($key)->label($label),
                                            };
                                            if (($def['is_required'] ?? false) && $type !== 'toggle') $field->required();
                                            $fields[] = $field;
                                        }

                                        return [
                                            Section::make('Informations requises par le fournisseur')
                                                ->schema($fields)->columns(2)
                                        ];
                                    }),
                            ])
                    ])
            ])->columnSpan(['lg' => 2]),

            Group::make()->schema([
                Section::make('Récapitulatif & Statut')
                    ->schema([
                        Placeholder::make('status_display')
                            ->label('Statut du dossier')
                            ->content(fn ($record) => $record?->status ? strtoupper($record->status) : 'EN ATTENTE (NOUVEAU)')
                            ->extraAttributes(['class' => 'text-primary-600 font-bold']),

                        Placeholder::make('total_price_display')
                            ->label('Montant total estimé (¥)')
                            ->content(function ($get) {
                                $items = $get('folderItems') ?? [];
                                $total = array_sum(array_map(fn($i) => (float)($i['total_price'] ?? 0), $items));
                                return number_format($total, 0, '.', ' ') . ' ¥';
                            })
                            ->extraAttributes(['class' => 'text-xl font-bold']),
                            
                        Hidden::make('total_price')->default(0),
                        Hidden::make('status')->default('pending'),
                        Hidden::make('folder_fee')->default(0),
                        Hidden::make('agency_id')->default(fn () => auth()->user()->agency_id),
                    ]),

                Section::make('Logistique d\'arrivée')
                    ->schema([
                        Textarea::make('flight_info')->label('Vols (Arrivée/Départ)')->rows(3),
                        TextInput::make('first_hotel_name')->label('1er Hôtel (Nom)'),
                        DatePicker::make('first_hotel_check_in')->label('Date Check-in 1er Hôtel'),
                        Select::make('ticket_dispatch_method')
                            ->label('Envoi de la billetterie')
                            ->options(['hotel' => 'Hôtel', 'guide' => 'Guide', 'autre' => 'Autre'])
                            ->live(),
                        TextInput::make('ticket_dispatch_other')
                            ->label('Lieu d\'envoi (si Autre)')
                            ->visible(fn ($get) => $get('ticket_dispatch_method') === 'autre'),
                    ])
            ])->columnSpan(['lg' => 1]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->label('Réf.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('folder_name')->label('Nom du dossier')->searchable(),
                Tables\Columns\TextColumn::make('lead_traveler_name')->label('Voyageur')->searchable(),
                Tables\Columns\TextColumn::make('start_date')->label('Arrivée')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('total_price')->label('Total Estimé')->money('JPY'),
            ])
            ->filters([])
            // 💡 SYNTAXE CORRIGÉE : Utilisation de recordActions() au lieu de actions()
            ->recordActions([
                EditAction::make()->label('Suivi / Modifier'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgencyFolders::route('/'),
            'create' => Pages\CreateAgencyFolder::route('/create'),
            'edit' => Pages\EditAgencyFolder::route('/{record}/edit'),
        ];
    }
}