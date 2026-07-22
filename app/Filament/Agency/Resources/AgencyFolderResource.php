<?php

namespace App\Filament\Agency\Resources;

use App\Filament\Agency\Resources\AgencyFolderResource\Pages;
use App\Models\Folder;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema; 
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Blade;
use Filament\Facades\Filament;

class AgencyFolderResource extends Resource
{
    protected static ?string $model = Folder::class;
    
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';
    
    public static function canViewAny(): bool
    {
        return Filament::auth()->check();
    }

    public static function canCreate(): bool
    {
        return Filament::auth()->check();
    }

    public static function canEdit(Model $record): bool
    {
        return Filament::auth()->check() && $record->agency_id === Filament::auth()->user()->agency_id;
    }

    public static function canView(Model $record): bool
    {
        return Filament::auth()->check() && $record->agency_id === Filament::auth()->user()->agency_id;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

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
        return parent::getEloquentQuery()->where('agency_id', Filament::auth()->user()->agency_id);
    }

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
                    ->columns(2)
                    ->schema([
                        TextInput::make('folder_name')
                            ->label('Nom du dossier / Réf. Groupe')
                            ->required(),

                        TextInput::make('lead_traveler_name')
                            ->label('Nom du pax principal')
                            ->required(),

                        TextInput::make('hotel_booking_name')
                            ->label('Nom réservation hôtel')
                            ->placeholder('Si différent du pax principal')
                            ->columnSpanFull(),

                        Select::make('main_seller_id')
                            ->label('Vendeur principal')
                            ->options(function () {
                                $agencyId = Filament::auth()->user()->agency_id;
                                if (!$agencyId) return [];
                                return \App\Models\User::where('agency_id', $agencyId)->pluck('name', 'id');
                            })
                            ->default(fn () => Filament::auth()->id()) // 💡 Pré-sélectionne l'agent connecté
                            ->searchable()
                            ->preload()
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
                            ->minDate(fn (Get $get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null),

                        Repeater::make('contact_phones')
                            ->label('Contact du pax pendant le séjour')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->defaultItems(1)
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Téléphone')
                                    ->tel()
                                    ->required()
                                    ->placeholder('+33 6...'),
                                TextInput::make('email')
                                    ->label('Adresse E-mail')
                                    ->email()
                                    ->placeholder('pax@email.com'),
                            ])
                            ->columnSpanFull()
                            ->columns(2),
                    ]),

                Section::make('Liste des Pax')
                    ->schema([
                        Repeater::make('folderPassengers')
                            ->relationship()
                            ->hiddenLabel()
                            ->addActionLabel('Ajouter un pax')
                            ->itemLabel(function (array $state) {
                                if (!empty($state['id'])) return new HtmlString("<span style='color:#096a61; font-weight:600;'>Pax enregistré</span>");
                                return new HtmlString("<span style='color:#d97706; font-weight:600;'>Nouveau pax...</span>");
                            })
                            ->schema([
                                Placeholder::make('condensed_passenger')
                                    ->hiddenLabel()
                                    ->visible(fn (Get $get) => !empty($get('id')))
                                    ->content(function (Get $get) {
                                        $pax = \App\Models\FolderPassenger::find($get('id'));
                                        if (!$pax) return '';
                                        $name = trim(($pax->first_name ?? '') . ' ' . ($pax->last_name ?? ''));
                                        $age = $pax->birth_date ? \Carbon\Carbon::parse($pax->birth_date)->age . ' ans' : '';
                                        $nat = $pax->nationality ?? '';

                                        $warns = [];
                                        if (!empty($pax->dietary_restrictions)) $warns[] = "🚫 Allergies: {$pax->dietary_restrictions}";
                                        if (!empty($pax->mobility_concerns)) $warns[] = "♿ PMR: {$pax->mobility_concerns}";
                                        $warnHtml = '';
                                        if (count($warns) > 0) {
                                            $warnHtml = "<div style='margin-top:0.5rem; font-size:0.8rem; color:#dc2626; font-weight:600;'>" . implode('<br>', $warns) . "</div>";
                                        }

                                        return new HtmlString("
                                            <div style='padding:0.75rem 1rem; border-left:4px solid #096a61; background:#f9fafb; border-radius:0 0.5rem 0.5rem 0;'>
                                                <div style='font-weight:bold; color:#111827; font-size:1.05rem;'>👤 {$name} <span style='font-weight:normal; color:#6b7280; font-size:0.9rem;'>({$age}) - {$nat}</span></div>
                                                {$warnHtml}
                                            </div>
                                        ");
                                    }),

                                Group::make()->schema([
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
                                ])->visible(fn (Get $get) => empty($get('id'))),
                            ])
                            ->deleteAction(fn ($action) => $action
                                ->label('Annuler')
                                ->icon('heroicon-m-x-mark')
                                ->color('danger')
                                ->hidden(fn (Get $get) => !empty($get('id')))
                            )
                    ]),

                Section::make('Prestations demandées')
                    ->description('Vos prestations réservées et le suivi de leur statut.')
                    ->headerActions([
                        Action::make('addPrestationModal')
                            ->label('Ajouter une prestation')
                            ->icon('heroicon-m-plus')
                            ->color('primary')
                            ->modalHeading('Ajouter une nouvelle prestation')
                            ->modalWidth('4xl')
                            ->modalSubmitActionLabel('Ajouter cette prestation au dossier')
                            ->visible(fn (?Model $record) => $record !== null)
                            ->form([
                                Select::make('product_id')
                                    ->label('Produit / Activité')
                                    ->options(function () {
                                        return \App\Models\Product::where('is_public', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $options = \App\Models\ProductOption::where('product_id', $state)->get();
                                            foreach ($options as $opt) {
                                                $set("opt_enabled_{$opt->id}", false);
                                                $set("opt_qty_{$opt->id}", 1);
                                            }
                                        }
                                    }),

                                Group::make()->schema([
                                    DatePicker::make('service_date')
                                        ->label('Date souhaitée')
                                        ->required()
                                        ->live()
                                        ->minDate(fn (?Model $record) => $record?->start_date ? Carbon::parse($record->start_date)->startOfDay() : null)
                                        ->maxDate(fn (?Model $record) => $record?->end_date ? Carbon::parse($record->end_date)->endOfDay() : null),

                                    TextInput::make('quantity')
                                        ->label('Participants (Nombre de Pax)')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->live()
                                        ->required(),
                                ])->columns(2)->visible(fn (Get $get) => !empty($get('product_id'))),

                                Group::make()
                                    ->visible(fn (Get $get) => !empty($get('product_id')) && \App\Models\ProductOption::where('product_id', $get('product_id'))->exists())
                                    ->schema(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) return [];

                                        $options = \App\Models\ProductOption::where('product_id', $productId)->get();
                                        if ($options->isEmpty()) return [];

                                        $schema = [];
                                        foreach ($options as $opt) {
                                            $optId = $opt->id;
                                            $formattedPrice = number_format($opt->price_modifier ?? 0, 0, '.', ' ') . ' ¥';
                                            
                                            $billingBadge = match ($opt->billing_type) {
                                                'per_pax' => "{$formattedPrice} / pax",
                                                'per_booking' => "{$formattedPrice} (Prix fixe)",
                                                'manual' => "{$formattedPrice} / unité",
                                                default => $formattedPrice,
                                            };

                                            $toggle = Toggle::make("opt_enabled_{$optId}")
                                                ->label("{$opt->name} (+ {$billingBadge})")
                                                ->inline(false)
                                                ->live();

                                            if ($opt->billing_type === 'manual') {
                                                $qtyInput = TextInput::make("opt_qty_{$optId}")
                                                    ->label('Quantité au choix')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->minValue(1)
                                                    ->required(fn (Get $get) => (bool) $get("opt_enabled_{$optId}"))
                                                    ->visible(fn (Get $get) => (bool) $get("opt_enabled_{$optId}"));

                                                $schema[] = Group::make()
                                                    ->schema([$toggle, $qtyInput])
                                                    ->columns(2);
                                            } else {
                                                $schema[] = $toggle;
                                            }
                                        }

                                        return [
                                            Section::make('Options tarifaires complémentaires')
                                                ->description('Cochez les options souhaitées selon leur mode de facturation.')
                                                ->schema($schema)
                                        ];
                                    }),

                                Group::make()
                                    ->visible(fn (Get $get) => !empty($get('product_id')))
                                    ->schema(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) return [];

                                        $product = \App\Models\Product::find($productId);
                                        if (!$product || empty($product->custom_field_definitions)) return [];

                                        $paxCount = max(1, (int) ($get('quantity') ?? 1));

                                        $fields = [];
                                        foreach ($product->custom_field_definitions as $def) {
                                            $type = $def['type'] ?? 'text';
                                            $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                                            $baseLabel = $def['name'] ?? 'Information';
                                            $isPerPax = $def['is_per_passenger'] ?? false;
                                            $isRequired = ($def['is_required'] ?? false) && $type !== 'toggle';

                                            if ($isPerPax) {
                                                for ($i = 1; $i <= $paxCount; $i++) {
                                                    $formKey = "custom_{$key}_pax_{$i}";
                                                    $label = "{$baseLabel} (Pax {$i})";

                                                    $field = match ($type) {
                                                        'textarea' => Textarea::make($formKey)->label($label)->rows(2),
                                                        'number' => TextInput::make($formKey)->numeric()->label($label),
                                                        'date' => DatePicker::make($formKey)->label($label),
                                                        'toggle' => Toggle::make($formKey)->label($label)->inline(false),
                                                        'select' => Select::make($formKey)->label($label)->options(array_combine($def['choices'] ?? [], $def['choices'] ?? [])),
                                                        default => TextInput::make($formKey)->label($label),
                                                    };
                                                    if ($isRequired) $field->required();
                                                    $fields[] = $field;
                                                }
                                            } else {
                                                $formKey = "custom_{$key}";
                                                $label = $baseLabel;

                                                $field = match ($type) {
                                                    'textarea' => Textarea::make($formKey)->label($label)->rows(2),
                                                    'number' => TextInput::make($formKey)->numeric()->label($label),
                                                    'date' => DatePicker::make($formKey)->label($label),
                                                    'toggle' => Toggle::make($formKey)->label($label)->inline(false),
                                                    'select' => Select::make($formKey)->label($label)->options(array_combine($def['choices'] ?? [], $def['choices'] ?? [])),
                                                    default => TextInput::make($formKey)->label($label),
                                                };
                                                if ($isRequired) $field->required();
                                                $fields[] = $field;
                                            }
                                        }
                                        
                                        if (empty($fields)) return [];

                                        return [
                                            Section::make('Informations requises par le fournisseur')
                                                ->schema($fields)->columns(2)
                                        ];
                                    }),
                            ])
                            ->action(function (array $data, ?Model $record) {
                                if (!$record) return;

                                $status = \App\Models\ItemStatus::firstOrCreate(
                                    ['name' => 'En attente de validation'],
                                    ['color' => 'warning']
                                );

                                $formattedOptions = [];
                                $options = \App\Models\ProductOption::where('product_id', $data['product_id'])->get();
                                foreach ($options as $opt) {
                                    if (!empty($data["opt_enabled_{$opt->id}"])) {
                                        $formattedOptions[] = [
                                            'product_option_id' => $opt->id,
                                            'quantity' => (int) ($data["opt_qty_{$opt->id}"] ?? 1),
                                        ];
                                    }
                                }

                                $customValues = [];
                                $product = \App\Models\Product::find($data['product_id']);
                                if ($product && !empty($product->custom_field_definitions)) {
                                    foreach ($product->custom_field_definitions as $def) {
                                        $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                                        $isPerPax = $def['is_per_passenger'] ?? false;
                                        $paxCount = max(1, (int) ($data['quantity'] ?? 1));

                                        if ($isPerPax) {
                                            $paxVals = [];
                                            for ($i = 1; $i <= $paxCount; $i++) {
                                                $fk = "custom_{$key}_pax_{$i}";
                                                if (isset($data[$fk]) && $data[$fk] !== '') {
                                                    $v = $data[$fk];
                                                    if (is_bool($v)) $v = $v ? 'Oui' : 'Non';
                                                    $paxVals[] = "Pax {$i}: {$v}";
                                                }
                                            }
                                            if (!empty($paxVals)) {
                                                $customValues[$key] = implode(', ', $paxVals);
                                            }
                                        } else {
                                            $fk = "custom_{$key}";
                                            if (isset($data[$fk])) {
                                                $customValues[$key] = $data[$fk];
                                            }
                                        }
                                    }
                                }

                                $folderItem = \App\Models\FolderItem::create([
                                    'folder_id' => $record->id,
                                    'product_id' => $data['product_id'],
                                    'service_date' => $data['service_date'],
                                    'quantity' => $data['quantity'] ?? 1,
                                    'selected_options' => $formattedOptions,
                                    'custom_values' => $customValues,
                                    'item_status_id' => $status->id,
                                    'unit_price' => 0,
                                    'total_price' => 0,
                                ]);

                                \App\Filament\Resources\Folders\FolderResource::updateItemPrices(
                                    function($k, $v) use ($folderItem) { $folderItem->update([$k => $v]); },
                                    function($k) use ($folderItem) { return $folderItem->{$k}; }
                                );

                                $record->refresh();
                                $totalSale = $record->folderItems->sum('total_price') + ($record->folder_fee ?? 0);
                                $record->update(['total_price' => $totalSale]);

                                \Filament\Notifications\Notification::make()
                                    ->title('Prestation ajoutée au dossier !')
                                    ->body('La prestation a été enregistrée avec le statut "En attente de validation".')
                                    ->success()
                                    ->send();
                            })
                    ])
                    ->schema([
                        Repeater::make('folderItems')
                            ->relationship()
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->itemLabel(function (array $state) {
                                return new HtmlString("<span style='color:#096a61; font-weight:600;'>Prestation enregistrée</span>");
                            })
                            ->schema([
                                Placeholder::make('condensed_item')
                                    ->hiddenLabel()
                                    ->content(function (Get $get) {
                                        $item = \App\Models\FolderItem::with(['product', 'itemStatus'])->find($get('id'));
                                        if (!$item) return '';

                                        $productName = $item->product->name ?? 'Prestation';
                                        $date = $item->service_date ? \Carbon\Carbon::parse($item->service_date)->format('d/m/Y') : 'À définir';
                                        $qty = $item->quantity ?? 1;

                                        $status = $item->itemStatus;
                                        $statusName = $status->name ?? 'En attente de validation';
                                        $colorName = $status->color ?? 'warning';
                                        $statusColor = match($colorName) {
                                            'success' => '#16a34a',
                                            'warning' => '#d97706',
                                            'danger' => '#dc2626',
                                            'info' => '#2563eb',
                                            default => '#d97706',
                                        };
                                        $statusBadge = "<span style='background:{$statusColor}15; color:{$statusColor}; padding:4px 12px; border-radius:99px; font-size:0.75rem; font-weight:bold; letter-spacing:0.05em; border:1px solid {$statusColor}30;'>📌 {$statusName}</span>";

                                        $optionsHtml = '';
                                        $selectedOptions = $item->selected_options ?? [];
                                        if (is_array($selectedOptions) && count($selectedOptions) > 0) {
                                            $opts = [];
                                            foreach ($selectedOptions as $optData) {
                                                if (!empty($optData['product_option_id'])) {
                                                    $optModel = \App\Models\ProductOption::find($optData['product_option_id']);
                                                    if ($optModel) {
                                                        $optQty = $optData['quantity'] ?? 1;
                                                        $qtyStr = $optModel->billing_type === 'manual' ? " (x{$optQty})" : "";
                                                        $opts[] = "• " . $optModel->name . $qtyStr;
                                                    }
                                                }
                                            }
                                            if (count($opts) > 0) {
                                                $optionsHtml = "<div style='margin-top:1rem; font-size:0.85rem; color:#4b5563;'><b>Options incluses :</b><br>" . implode('<br>', $opts) . "</div>";
                                            }
                                        }

                                        $customValuesHtml = '';
                                        $customValues = $item->custom_values ?? [];
                                        $product = $item->product;
                                        if ($product && !empty($product->custom_field_definitions) && is_array($customValues) && count($customValues) > 0) {
                                            $cvs = [];
                                            foreach ($product->custom_field_definitions as $def) {
                                                $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                                                $label = $def['name'] ?? 'Information';
                                                
                                                if (isset($customValues[$key]) && $customValues[$key] !== '') {
                                                    $val = $customValues[$key];
                                                    
                                                    if (is_bool($val)) {
                                                        $val = $val ? 'Oui' : 'Non';
                                                    } elseif (is_array($val)) {
                                                        $val = implode(', ', \Illuminate\Support\Arr::flatten($val));
                                                    }
                                                    
                                                    $cvs[] = "<b>{$label} :</b> {$val}";
                                                }
                                            }
                                            if (count($cvs) > 0) {
                                                $customValuesHtml = "<div style='margin-top:0.75rem; font-size:0.85rem; color:#4b5563; padding-top:0.75rem; border-top:1px dashed #e5e7eb;'>" . implode('<br>', $cvs) . "</div>";
                                            }
                                        }

                                        return new HtmlString("
                                            <div style='padding:1.25rem; border-radius:0.75rem; border:1px solid #e5e7eb; background:#ffffff; box-shadow:0 1px 3px rgba(0,0,0,0.05);'>
                                                <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;'>
                                                    <strong style='font-size:1.15rem; color:#111827;'>{$productName}</strong>
                                                    {$statusBadge}
                                                </div>
                                                <div style='display:flex; gap:2rem; font-size:0.95rem; color:#374151; background:#f3f4f6; padding:0.75rem 1.25rem; border-radius:0.5rem;'>
                                                    <span>📅 Date : <b>{$date}</b></span>
                                                    <span>👥 Pax : <b>{$qty}</b></span>
                                                </div>
                                                {$optionsHtml}
                                                {$customValuesHtml}
                                            </div>
                                        ");
                                    }),
                            ]),
                    ]),
            ])->columnSpan(['lg' => 2]),

            Group::make()->schema([
                Section::make('Récapitulatif & Statut')
                    ->schema([
                        Placeholder::make('status_display')
                            ->label('Statut du dossier')
                            ->content(function ($record) {
                                if (!$record || !$record->status) return 'EN ATTENTE (NOUVEAU)';
                                return match ($record->status) {
                                    'draft' => 'BROUILLON',
                                    'pending' => 'EN ATTENTE DE VALIDATION',
                                    'confirmed' => 'CONFIRMÉ / VALIDÉ',
                                    'completed' => 'VOYAGE TERMINÉ',
                                    'cancelled' => 'ANNULÉ',
                                    default => strtoupper($record->status),
                                };
                            })
                            ->extraAttributes(['class' => 'text-primary-600 font-bold']),

                        Placeholder::make('total_price_display')
                            ->label('Montant total estimé (¥)')
                            ->content(function (Get $get) {
                                $items = $get('folderItems') ?? [];
                                $total = array_sum(array_map(fn($i) => (float)($i['total_price'] ?? 0), $items));
                                return number_format($total, 0, '.', ' ') . ' ¥';
                            })
                            ->extraAttributes(['class' => 'text-xl font-bold']),
                            
                        Hidden::make('total_price')->default(0),
                        Hidden::make('status')->default('pending'),
                        Hidden::make('folder_fee')->default(0),
                        Hidden::make('agency_id')->default(fn () => Filament::auth()->user()->agency_id),
                    ]),

                Section::make('Logistique d\'arrivée')
                    ->schema([
                        Textarea::make('flight_info')->label('Vols (Arrivée/Départ)')->rows(3),
                        TextInput::make('first_hotel_name')->label('1er Hôtel (Nom)'),

                        DatePicker::make('first_hotel_check_in')
                            ->label('Date Check-in 1er Hôtel')
                            ->live()
                            ->minDate(fn (Get $get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null)
                            ->maxDate(fn (Get $get) => $get('end_date') ? Carbon::parse($get('end_date'))->endOfDay() : null)
                            ->afterOrEqual('start_date')
                            ->beforeOrEqual('end_date')
                            ->validationMessages([
                                'after_or_equal' => 'Doit être après ou le jour de l\'arrivée.',
                                'before_or_equal' => 'Doit être avant ou le jour du départ.',
                            ]),

                        Textarea::make('first_hotel_address')
                            ->label('Adresse du premier hôtel')
                            ->placeholder('Adresse complète pour l\'envoi éventuel de documents...')
                            ->rows(2),
                    ]),

            ])->columnSpan(['lg' => 1]),

            Section::make('Communication avec l\'équipe Takada')
                ->description('Échangez directement avec nous ici pour toute question ou envoi de fichier sur ce dossier.')
                ->schema([
                    Placeholder::make('chat_placeholder')
                        ->hiddenLabel()
                        ->content(fn (?Model $record) => $record ? new HtmlString(
                            Blade::render('@livewire("folder-chat", ["folder" => $folder])', ['folder' => $record])
                        ) : '')
                ])
                ->hidden(fn (?Model $record) => $record === null)
                ->columnSpanFull(),

        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->label('Réf.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('folder_name')->label('Nom du dossier')->searchable(),
                Tables\Columns\TextColumn::make('lead_traveler_name')->label('Pax Principal')->searchable(),
                Tables\Columns\TextColumn::make('mainSeller.name')->label('Vendeur Principal')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('start_date')->label('Arrivée')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Brouillon',
                        'pending' => 'En attente',
                        'confirmed' => 'Confirmé',
                        'completed' => 'Terminé',
                        'cancelled' => 'Annulé',
                        default => $state,
                    })
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