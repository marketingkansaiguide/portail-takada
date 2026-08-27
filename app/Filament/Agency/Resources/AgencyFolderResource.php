<?php

namespace App\Filament\Agency\Resources;

use App\Filament\Agency\Resources\AgencyFolderResource\Pages;
use App\Models\Folder;
use App\Models\Hotel;
use App\Services\FolderDriveStorage;
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

    private static function storeServiceFileOnGoogleDrive(\Filament\Forms\Components\FileUpload $upload): \Filament\Forms\Components\FileUpload
    {
        return $upload
            ->getUploadedFileUsing(function (\Filament\Forms\Components\FileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                $folder = $component->getRecord();
                $storage = app(FolderDriveStorage::class);

                return $folder instanceof Folder
                    ? $storage->uploadedFileInfo($folder, $file, $storedFileNames) ?? $component->getUploadedFile($file, $storedFileNames)
                    : null;
            })
            ->saveUploadedFileUsing(function (\Filament\Forms\Components\FileUpload $component, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): ?string {
                $folder = $component->getRecord();

                return $folder instanceof Folder
                    ? app(FolderDriveStorage::class)->upload($folder, $file, 'services')
                    : null;
            })
            ->deleteUploadedFileUsing(function (string $file): void {
                if (app(FolderDriveStorage::class)->isDrivePath($file)) {
                    app(FolderDriveStorage::class)->delete($file);
                }
            });
    }

    public static function canView(Model $record): bool
    {
        return Filament::auth()->check() && $record->agency_id === Filament::auth()->user()->agency_id;
    }

    public static function canDelete(Model $record): bool
    {
        return Filament::auth()->check() 
            && $record->agency_id === Filament::auth()->user()->agency_id 
            && $record->status === 'draft';
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
            
            Section::make()
                ->visible(fn (?Model $record) => $record && $record->status === 'draft')
                ->schema([
                    Placeholder::make('draft_notice')
                        ->hiddenLabel()
                        ->content(new HtmlString("
                            <div style='display: flex; align-items: center; justify-content: space-between; background-color: #fef3c7; border: 1px solid #f59e0b; padding: 12px 16px; border-radius: 8px; color: #92400e;'>
                                <div>
                                    <strong style='font-size: 1rem;'>" . e(__('✏️ Dossier en cours de rédaction (Brouillon)')) . "</strong>
                                    <p style='margin: 4px 0 0 0; font-size: 0.875rem;'>" . e(__('Vous pouvez modifier ou supprimer vos prestations et informations passagers librement. Une fois votre sélection finalisée, cliquez sur le bouton')) . " <b>\"🚀 " . e(__('Valider et transmettre le dossier')) . "\"</b> " . e(__('en haut à droite.')) . "</p>
                                </div>
                            </div>
                        "))
                ])
                ->columnSpanFull(),

            Group::make()->schema([
            Section::make(__('Informations Principales'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('folder_name')
                            ->label(__('Nom du dossier / Réf. Groupe'))
                            ->required(),

                        TextInput::make('lead_traveler_name')
                            ->label(__('Nom complet du pax principal'))
                            ->required(),

                        TextInput::make('hotel_booking_name')
                            ->label(__('Nom complet réservation hôtel'))
                            ->placeholder(__('Si différent du pax principal'))
                            ->columnSpanFull(),

                        Select::make('main_seller_id')
                            ->label(__('Vendeur principal'))
                            ->options(function () {
                                $agencyId = Filament::auth()->user()->agency_id;
                                if (!$agencyId) return [];
                                return \App\Models\User::where('agency_id', $agencyId)->pluck('name', 'id');
                            })
                            ->default(fn () => Filament::auth()->id())
                            ->searchable()
                            ->preload()
                            ->required(),

                        DatePicker::make('start_date')
                            ->label(__('Date d\'arrivée au Japon'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get)),

                        DatePicker::make('end_date')
                            ->label(__('Date de départ'))
                            ->required()
                            ->live()
                            ->minDate(fn (Get $get) => $get('start_date') ? Carbon::parse($get('start_date'))->startOfDay() : null),

                        Repeater::make('contact_phones')
                            ->label(__('Contact du pax pendant le séjour'))
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->defaultItems(1)
                            ->schema([
                                TextInput::make('phone')
                                    ->label(__('Téléphone'))
                                    ->tel()
                                    ->required()
                                    ->placeholder(__('+33 6...')),
                                TextInput::make('email')
                                    ->label(__('Adresse E-mail'))
                                    ->email()
                                    ->placeholder(__('pax@email.com')),
                            ])
                            ->columnSpanFull()
                            ->columns(2),
                    ]),

            Section::make(__('Informations du Premier Hébergement'))
                    ->columns(2)
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
                                    $set('first_hotel_google_maps_url', $hotel->google_maps_url);
                                }
                            })
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        TextInput::make('first_hotel_name')
                            ->label(__('1er Hébergement (Nom)'))
                            ->placeholder(__('Ex: Hotel Gracery Shinjuku')),

                        DatePicker::make('first_hotel_check_in')
                            ->label(__('Date Check-in 1er Hébergement'))
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
                            ->label(__('Adresse du premier hébergement'))
                            ->placeholder(__('Adresse complète pour l\'envoi éventuel de documents...'))
                            ->rows(2)
                            ->columnSpanFull(),

                        TextInput::make('first_hotel_google_maps_url')
                            ->label(__('Lien Google Maps'))
                            ->url()
                            ->placeholder(__('https://maps.google.com/...'))
                            ->live()
                            ->columnSpanFull(),

                        Placeholder::make('first_hotel_google_maps_link')
                            ->hiddenLabel()
                            ->visible(fn (Get $get) => !empty($get('first_hotel_google_maps_url')))
                            ->content(function (Get $get) {
                                $url = $get('first_hotel_google_maps_url');
                                if (!$url) return '';
                                return new HtmlString("
                                    <div style='margin-top: 4px;'>
                                        <a href='{$url}' target='_blank' style='display: inline-flex; align-items: center; gap: 8px; background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 8px 14px; border-radius: 6px; font-weight: 600; font-size: 0.875rem; text-decoration: none;'>
                                            📍 Ouvrir la localisation sur Google Maps ↗
                                        </a>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),
                    ]),

            Section::make(__('Liste des Pax'))
                    ->schema([
                        Repeater::make('folderPassengers')
                            ->relationship()
                            ->hiddenLabel()
                            ->addActionLabel(__('Ajouter un passager'))
                            ->addable(fn (?Model $record, Get $get) => $record === null || $record->status === 'draft' || $get('status') === 'draft')
                            ->deletable(fn (?Model $record, Get $get) => $record === null || $record->status === 'draft' || $get('status') === 'draft')
                            ->reorderable(false)
                            ->itemLabel(function (array $state) {
                                $name = trim(($state['first_name'] ?? '') . ' ' . ($state['last_name'] ?? ''));
                                if (!empty($name)) {
                                    return new HtmlString("<span style='color:#096a61; font-weight:600;'>👤 {$name}</span>");
                                }
                                return new HtmlString("<span style='color:#4b5563; font-weight:600;'>👤 Passager (à renseigner)</span>");
                            })
                            ->schema([
                                Placeholder::make('condensed_passenger')
                                    ->hiddenLabel()
                                    ->visible(fn (?Model $record, Get $get) => $record !== null && $record->status !== 'draft' && $get('../../status') !== 'draft')
                                    ->content(function (Get $get) {
                                        $paxId = $get('id');
                                        $pax = $paxId ? \App\Models\FolderPassenger::find($paxId) : null;
                                        $firstName = $get('first_name') ?? $pax?->first_name ?? '';
                                        $lastName = $get('last_name') ?? $pax?->last_name ?? '';
                                        $birthDate = $get('birth_date') ?? $pax?->birth_date;
                                        $nat = $get('nationality') ?? $pax?->nationality ?? '';
                                        $diet = $get('dietary_restrictions') ?? $pax?->dietary_restrictions;
                                        $mobility = $get('mobility_concerns') ?? $pax?->mobility_concerns;

                                        $name = trim($firstName . ' ' . $lastName);
                                        $age = $birthDate ? \Carbon\Carbon::parse($birthDate)->age . ' ans' : '';

                                        $warns = [];
                                        if (!empty($diet)) $warns[] = "🚫 Allergies: {$diet}";
                                        if (!empty($mobility)) $warns[] = "♿ PMR: {$mobility}";
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
                                        TextInput::make('last_name')->label(__('Nom'))->required(),
                                        TextInput::make('first_name')->label(__('Prénom'))->required(),
                                        DatePicker::make('birth_date')
                                            ->label(__('Date de naissance'))
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn ($set, $get) => self::updatePassengerCount($set, $get)),
                                        TextInput::make('nationality')->label(__('Nationalité'))->required(),
                                    ])->columns(4),
                                    Textarea::make('dietary_restrictions')->label(__('Allergies'))->rows(1),
                                    Textarea::make('mobility_concerns')->label(__('Besoins PMR'))->rows(1),
                                ])->visible(fn (?Model $record, Get $get) => $record === null || $record->status === 'draft' || $get('../../status') === 'draft'),
                            ])
                            ->deleteAction(fn ($action) => $action
                                ->label(__('Annuler'))
                                ->icon('heroicon-m-x-mark')
                                ->color('danger')
                                ->visible(fn (?Model $record, Get $get) => $record === null || $record->status === 'draft' || $get('../../status') === 'draft')
                            )
                    ]),

            Section::make(__('Prestations demandées'))
                    ->description(__('Vos prestations réservées et le suivi de leur statut.'))
                    ->visible(fn (?Model $record) => $record !== null)
                    ->headerActions([
                        Action::make('addPrestationModal')
                            ->label(__('Ajouter une prestation'))
                            ->icon('heroicon-m-plus')
                            ->color('primary')
                            ->modalHeading(__('Ajouter une nouvelle prestation'))
                            ->modalWidth('4xl')
                            ->modalSubmitActionLabel(__('Ajouter cette prestation au dossier'))
                            ->visible(fn (?Model $record) => $record !== null)
                            ->form([
                                Select::make('product_id')
                                    ->label(__('Produit / Activité'))
                                    ->options(function () {
                                        $agencyId = \Filament\Facades\Filament::auth()->user()?->agency_id
                                            ?? auth('agency')->user()?->agency_id;

                                        return \App\Models\Product::where('is_public', true)
                                            ->availableForAgency($agencyId)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $options = \App\Models\ProductOption::where('product_id', $state)->get();
                                            
                                            $requiredGrouped = $options->filter(fn($o) => $o->is_required || !empty($o->group_name))
                                                ->groupBy(fn($o) => !empty($o->group_name) ? $o->group_name : 'default_required');

                                            foreach ($requiredGrouped as $groupName => $groupOpts) {
                                                $firstOpt = $groupOpts->first();
                                                $set("opt_group_" . \Illuminate\Support\Str::slug($groupName), $firstOpt->id);
                                            }

                                            foreach ($options as $opt) {
                                                $isFirstRequired = false;
                                                foreach ($requiredGrouped as $groupOpts) {
                                                    if ($groupOpts->first()->id === $opt->id) {
                                                        $isFirstRequired = true;
                                                        break;
                                                    }
                                                }

                                                $set("opt_enabled_{$opt->id}", $isFirstRequired);
                                                $set("opt_qty_{$opt->id}", 1);
                                            }
                                        }
                                    }),

                                // 💡 SECTION DÉDIÉE SI C'EST UN PRODUIT DE TRANSPORT (BILLET DE TRAIN / BUS)
            Section::make(__('🚄 Itinéraire de Transport (Multi-Trajets)'))
                    ->description(__('Indiquez pour chaque ticket la gare de départ, d\'arrivée, la date, l\'heure et le nombre de passagers.'))
                                    ->visible(function (Get $get, $livewire) {
                                        $productId = $get('product_id')
                                                  ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                  ?? data_get($livewire, 'mountedActionData.product_id');
                                        if (!$productId) return false;
                                        $p = \App\Models\Product::find($productId);
                                        return $p && $p->product_type === 'transport';
                                    })
                                    ->schema([

Repeater::make('transport_routes')
    ->hiddenLabel()
                        ->addActionLabel(__('Ajouter un trajet'))
    ->itemLabel(fn (array $state): ?string => 
        (!empty($state['departure_station']) && !empty($state['arrival_station']))
            ? "{$state['departure_station']} ➔ {$state['arrival_station']}" . (!empty($state['departure_date']) ? " ({$state['departure_date']})" : "")
            : 'Nouveau trajet'
    )
    ->collapsible()
    ->required()
    ->defaultItems(1)
    ->live()
    ->schema([
        // -------------------------------------------------------------
        // LIGNE 1 : DÉPART ET ARRIVÉE (Gares & Stations) - 2 Colonnes
        // -------------------------------------------------------------
        Group::make()->schema([
            Select::make('departure_station')
                                ->label(__('Gare / Station de départ'))
                ->searchable()
                ->required()
                ->live()
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
                ->getOptionLabelUsing(function ($value) {
                    $station = \App\Models\TrainStation::where('name_en', $value)->first() ?? \App\Models\BusStation::where('name_en', $value)->first();
                    if ($station) {
                        $type = $station instanceof \App\Models\TrainStation ? '🚆' : '🚌 [Bus]';
                        $locationSuffix = ($station instanceof \App\Models\TrainStation && $station->city) ? " - {$station->city}" : "";
                        return "{$type} {$station->name_en}" . ($station->name_ja ? " ({$station->name_ja})" : "") . $locationSuffix;
                    }
                    return $value;
                })
                ->helperText(function (\Filament\Schemas\Components\Utilities\Get $get) {
                    $stationName = $get('departure_station');
                    if (!$stationName) return null;
                    
                    $station = \App\Models\TrainStation::where('name_en', $stationName)->first() 
                            ?? \App\Models\BusStation::where('name_en', $stationName)->first();
                            
                    if ($station && !empty($station->google_maps_url)) {
                        return new \Illuminate\Support\HtmlString("<a href='{$station->google_maps_url}' target='_blank' style='color: #2563eb; text-decoration: underline; font-size: 0.8rem; display: flex; align-items: center; gap: 4px; margin-top: 4px;'><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor' style='width: 14px; height: 14px;'><path fill-rule='evenodd' d='M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.018.008.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z' clip-rule='evenodd' /></svg> Voir sur Google Maps</a>");
                    }
                    return null;
                }),

            Select::make('arrival_station')
                                ->label(__('Gare / Station d\'arrivée'))
                ->searchable()
                ->required()
                ->live()
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
                ->getOptionLabelUsing(function ($value) {
                    $station = \App\Models\TrainStation::where('name_en', $value)->first() ?? \App\Models\BusStation::where('name_en', $value)->first();
                    if ($station) {
                        $type = $station instanceof \App\Models\TrainStation ? '🚆' : '🚌 [Bus]';
                        $locationSuffix = ($station instanceof \App\Models\TrainStation && $station->city) ? " - {$station->city}" : "";
                        return "{$type} {$station->name_en}" . ($station->name_ja ? " ({$station->name_ja})" : "") . $locationSuffix;
                    }
                    return $value;
                })
                ->helperText(function (\Filament\Schemas\Components\Utilities\Get $get) {
                    $stationName = $get('arrival_station');
                    if (!$stationName) return null;
                    
                    $station = \App\Models\TrainStation::where('name_en', $stationName)->first() 
                            ?? \App\Models\BusStation::where('name_en', $stationName)->first();
                            
                    if ($station && !empty($station->google_maps_url)) {
                        return new \Illuminate\Support\HtmlString("<a href='{$station->google_maps_url}' target='_blank' style='color: #2563eb; text-decoration: underline; font-size: 0.8rem; display: flex; align-items: center; gap: 4px; margin-top: 4px;'><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor' style='width: 14px; height: 14px;'><path fill-rule='evenodd' d='M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.018.008.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z' clip-rule='evenodd' /></svg> Voir sur Google Maps</a>");
                    }
                    return null;
                }),
        ])->columns(2),

        // -------------------------------------------------------------
        // LIGNE 2 : DATES, CLASSE & PASSAGERS - 3 Colonnes
        // -------------------------------------------------------------
        Group::make()->schema([
            DatePicker::make('departure_date')
                            ->label(__('Date du trajet'))
                ->native(false)
                ->required()
                ->live(),

            Select::make('option_id')
                            ->label(__('Classe'))
                ->options(function (\Filament\Schemas\Components\Utilities\Get $get, $livewire) {
                    $productId = $get('../../product_id') 
                              ?? data_get($livewire, 'mountedTableActionData.product_id') 
                              ?? data_get($livewire, 'mountedActionData.product_id');
                              
                    if (!$productId) return [];
                    return \App\Models\ProductOption::where('product_id', $productId)->pluck('name', 'id');
                })
                ->searchable()
                ->required(),

            TextInput::make('pax_count')
                            ->label(__('Passagers (Pax)'))
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->live()
                ->required(),
        ])->columns(3),

        // -------------------------------------------------------------
        // LIGNE 3 : HORAIRES & DÉTAILS SOUHAITÉS - 3 Colonnes
        // -------------------------------------------------------------
        Group::make()->schema([
            TextInput::make('departure_time')
                            ->label(__('Heure de départ souhaitée'))
                            ->placeholder(__('Ex: 09:30')),

            TextInput::make('arrival_time')
                            ->label(__('Heure d\'arrivée souhaitée'))
                            ->placeholder(__('Ex: 12:15')),

            TextInput::make('train_number')
                            ->label(__('N° Train / Bus (Optionnel)'))
                            ->placeholder(__('Ex: Hikari 502 / Voiture 3')),
        ])->columns(3),
    ]),
                                        // 💡 Affichage du Prix Estimé pour les Transports (Frais fixes + Devis)
                                        Placeholder::make('transport_estimate')
                                            ->hiddenLabel()
                                            ->content(function (Get $get, $livewire) {
                                                $productId = $get('product_id') ?? data_get($livewire, 'mountedTableActionData.product_id') ?? data_get($livewire, 'mountedActionData.product_id');
                                                $product = \App\Models\Product::with(['productPeriods.productPrices'])->find($productId);
                                                if (!$product) return '';

                                                $routes = $get('transport_routes') ?? [];
                                                $totalPax = 0;
                                                foreach ($routes as $r) {
                                                    $totalPax += (int) ($r['pax_count'] ?? 1);
                                                }

                                                $date = $routes[0]['departure_date'] ?? now()->format('Y-m-d');
                                                $mdStr = \Carbon\Carbon::parse($date)->format('m-d');

                                                $feePerPax = 0;
                                                if ($product->productPeriods) {
                                                    foreach ($product->productPeriods as $period) {
                                                        $inPeriod = true; // Par défaut : Toute l'année
                                                        
                                                        if ($period->start_date && $period->end_date) {
                                                            $inPeriod = ($period->start_date <= $period->end_date) 
                                                                ? ($mdStr >= $period->start_date && $mdStr <= $period->end_date)
                                                                : ($mdStr >= $period->start_date || $mdStr <= $period->end_date);
                                                        }
                                                        
                                                        if ($inPeriod && $period->productPrices) {
                                                            $validPrices = $period->productPrices->where('min_pax', '<=', $totalPax)->where('max_pax', '>=', $totalPax);
                                                            $feePerPax = $validPrices->isNotEmpty() ? $validPrices->first()->price : ($period->productPrices->sortByDesc('max_pax')->first()->price ?? 0);
                                                            break;
                                                        }
                                                    }
                                                }

                                                $totalFee = $feePerPax * $totalPax;

                                                return new \Illuminate\Support\HtmlString("
                                                    <div style='padding: 1rem; border-radius: 0.5rem; background-color: #fff7ed; border: 1px solid #ffedd5; margin-top: 1rem;'>
                                                        <div style='font-weight:600; color:#c2410c; font-size:0.9rem; margin-bottom:0.35rem;'>" . e(__('Estimation de vos frais d’émission :')) . "</div>
                                                        <div style='display:flex; justify-content:space-between; font-size:0.875rem; color:#9a3412;'>
                                                            <span>• Frais de service de l'agence (" . number_format($feePerPax, 0, '.', ' ') . " ¥ × {$totalPax} trajet(s) total)</span>
                                                            <span><b>" . number_format($totalFee, 0, '.', ' ') . " ¥</b></span>
                                                        </div>
                                                        <div style='display:flex; justify-content:space-between; font-size:0.875rem; color:#9a3412; margin-top: 4px; border-top: 1px solid #fed7aa; padding-top: 4px;'>
                                                            <span>• Prix des billets (Train / Bus)</span>
                                                            <span><b>Sur devis (Prix coutant)</b></span>
                                                        </div>
                                                    </div>
                                                ");
                                            })
                                    ]),
                                // CHAMPS GLOBAUX S'IL S'AGIT D'UN PRODUIT STANDARD
                                Group::make()->schema([
                                    DatePicker::make('service_date')
                                        ->label(__('Date souhaitée'))
                                        ->required(function (Get $get, $livewire) {
                                            $productId = $get('product_id')
                                                      ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                      ?? data_get($livewire, 'mountedActionData.product_id');
                                            $p = $productId ? \App\Models\Product::find($productId) : null;
                                            return !$p || $p->product_type !== 'transport';
                                        })
                                        ->live()
                                        ->minDate(fn (?Model $record) => $record?->start_date ? Carbon::parse($record->start_date)->startOfDay() : null)
                                        ->maxDate(fn (?Model $record) => $record?->end_date ? Carbon::parse($record->end_date)->endOfDay() : null),

                                    TextInput::make('age_selector_adults')
                                        ->label(__('Adultes'))
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(0)
                                        ->live()
                                        ->afterStateHydrated(function ($component, $state, Get $get): void {
                                            $stored = data_get($get('custom_values'), '_age_selector.adults');
                                            if ($stored !== null) {
                                                $component->state((int) $stored);
                                            }
                                        })
                                        ->visible(function (Get $get, $livewire): bool {
                                            $productId = $get('product_id')
                                                      ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                      ?? data_get($livewire, 'mountedActionData.product_id');
                                            return (bool) \App\Models\Product::find($productId)?->usesAgeSelector();
                                        })
                                        ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get))
                                        ->columnSpan(1),

                                    TextInput::make('age_selector_children')
                                        ->label(__('Enfants'))
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->live()
                                        ->afterStateHydrated(function ($component, $state, Get $get): void {
                                            $stored = data_get($get('custom_values'), '_age_selector.children');
                                            if ($stored !== null) {
                                                $component->state((int) $stored);
                                            }
                                        })
                                        ->visible(function (Get $get, $livewire): bool {
                                            $productId = $get('product_id')
                                                      ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                      ?? data_get($livewire, 'mountedActionData.product_id');
                                            return (bool) \App\Models\Product::find($productId)?->hasChildSelector();
                                        })
                                        ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get))
                                        ->columnSpan(1),

                                    TextInput::make('age_selector_teenagers')
                                        ->label(__('Adolescents'))
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->live()
                                        ->afterStateHydrated(function ($component, $state, Get $get): void {
                                            $stored = data_get($get('custom_values'), '_age_selector.teenagers');
                                            if ($stored !== null) {
                                                $component->state((int) $stored);
                                            }
                                        })
                                        ->visible(function (Get $get, $livewire): bool {
                                            $productId = $get('product_id')
                                                      ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                      ?? data_get($livewire, 'mountedActionData.product_id');
                                            $product = \App\Models\Product::find($productId);
                                            return (bool) $product?->hasTeenSelector();
                                        })
                                        ->afterStateUpdated(fn ($set, $get) => self::updateItemPrices($set, $get))
                                        ->columnSpan(1),

                                    Placeholder::make('age_selector_hint')
                                        ->hiddenLabel()
                                        ->content(function (Get $get, $livewire) {
                                            $productId = $get('product_id')
                                                      ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                      ?? data_get($livewire, 'mountedActionData.product_id');
                                            $product = \App\Models\Product::find($productId);
                                            if (!$product?->usesAgeSelector()) {
                                                return null;
                                            }

                                            $childLimit = (int) ($product->child_age_limit ?? 11);
                                            $childHint = $product->hasChildSelector()
                                                ? 'Enfant = jusqu\'à : <strong>' . $childLimit . ' ans inclus</strong>'
                                                : '';
                                            $teenHint = $product->hasTeenSelector()
                                                ? ($childHint !== '' ? ' · ' : '') . __('Adolescent = de :min à :max ans', ['min' => $childLimit + 1, 'max' => (int) $product->teen_age_limit])
                                                : '';

                                            return new \Illuminate\Support\HtmlString(
                                                '<span style="font-size:0.8rem;color:#64748b;">' . $childHint . e($teenHint) . '</span>'
                                            );
                                        })
                                        ->visible(function (Get $get, $livewire): bool {
                                            $productId = $get('product_id')
                                                      ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                      ?? data_get($livewire, 'mountedActionData.product_id');
                                            return (bool) \App\Models\Product::find($productId)?->usesAgeSelector();
                                        })
                                        ->columnSpan(2),

                                    TextInput::make('quantity')
                                        ->label(__('Participants (Nombre de Pax)'))
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
                                        ->live()
                                        ->readOnly(function (Get $get, $livewire): bool {
                                            $productId = $get('product_id')
                                                      ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                      ?? data_get($livewire, 'mountedActionData.product_id');
                                            return (bool) \App\Models\Product::find($productId)?->enable_age_selector;
                                        })
                                        ->required(),
                                ])->columns(2)->visible(function (Get $get, $livewire) {
                                    $productId = $get('product_id')
                                              ?? data_get($livewire, 'mountedTableActionData.product_id')
                                              ?? data_get($livewire, 'mountedActionData.product_id');
                                    if (!$productId) return false;
                                    $p = \App\Models\Product::find($productId);
                                    return !$p || $p->product_type !== 'transport';
                                }),

                                Group::make()
                                    ->visible(function (Get $get, $livewire) {
                                        $productId = $get('product_id')
                                                  ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                  ?? data_get($livewire, 'mountedActionData.product_id');
                                        if (!$productId) return false;
                                        $p = \App\Models\Product::find($productId);
                                        return (!$p || $p->product_type !== 'transport') && \App\Models\ProductOption::where('product_id', $productId)->exists();
                                    })
                                    ->schema(function (Get $get, $livewire) {
                                        $productId = $get('product_id')
                                                  ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                  ?? data_get($livewire, 'mountedActionData.product_id');
                                        if (!$productId) return [];

                                        $options = \App\Models\ProductOption::where('product_id', $productId)->get();
                                        if ($options->isEmpty()) return [];

                                        $schema = [];

                                        $requiredGrouped = $options->filter(fn($o) => $o->is_required || !empty($o->group_name))
                                            ->groupBy(fn($o) => !empty($o->group_name) ? $o->group_name : 'default_required');

                                        if ($requiredGrouped->count() > 0) {
                                            foreach ($requiredGrouped as $groupName => $groupOptions) {
                                                $displayGroupName = ($groupName === 'default_required') ? 'Déclinaison' : $groupName;
                                                
                                                $selectOptions = [];
                                                foreach ($groupOptions as $opt) {
                                                    $product = $productId ? \App\Models\Product::find($productId) : null;
                                                    $priceLabel = ($product?->usesAgeSelector() && $opt->billing_type === 'per_pax')
                                                        ? " (+ " . number_format($opt->priceModifierForAge(false), 0, '.', ' ') . " ¥ adulte" . ($product->hasChildSelector() ? " / " . number_format($opt->priceModifierForAge(true), 0, '.', ' ') . " ¥ enfant" : '') . ($product->hasTeenSelector() ? " / " . number_format($opt->priceModifierForAgeGroup('teen'), 0, '.', ' ') . " ¥ adolescent" : '') . ")"
                                                        : ($opt->price_modifier > 0 ? " (+ " . number_format($opt->price_modifier, 0, '.', ' ') . " ¥)" : "");
                                                    $selectOptions[$opt->id] = $opt->name . $priceLabel;
                                                }

                                                $schema[] = Select::make("opt_group_" . \Illuminate\Support\Str::slug($groupName))
                                                    ->label($displayGroupName)
                                                    ->options($selectOptions)
                                                    ->default($groupOptions->first()->id)
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Set $set) use ($groupOptions) {
                                                        foreach ($groupOptions as $opt) {
                                                            $set("opt_enabled_{$opt->id}", false);
                                                        }
                                                        if ($state) {
                                                            $set("opt_enabled_{$state}", true);
                                                        }
                                                    });

                                                foreach ($groupOptions as $opt) {
                                                    $schema[] = Hidden::make("opt_enabled_{$opt->id}");
                                                    $schema[] = Hidden::make("opt_qty_{$opt->id}");
                                                }
                                            }
                                        }

                                        $optionalOptions = $options->filter(fn($o) => !$o->is_required && empty($o->group_name));

                                        if ($optionalOptions->count() > 0) {
                                            $optionalSchema = [];
                                            foreach ($optionalOptions as $opt) {
                                                $optId = $opt->id;
                                                $product = $productId ? \App\Models\Product::find($productId) : null;
                                                $formattedPrice = ($product?->usesAgeSelector() && $opt->billing_type === 'per_pax')
                                                    ? number_format($opt->priceModifierForAge(false), 0, '.', ' ') . ' ¥ adulte' . ($product->hasChildSelector() ? ' / ' . number_format($opt->priceModifierForAge(true), 0, '.', ' ') . ' ¥ enfant' : '') . ($product->hasTeenSelector() ? ' / ' . number_format($opt->priceModifierForAgeGroup('teen'), 0, '.', ' ') . ' ¥ adolescent' : '')
                                                    : number_format($opt->price_modifier ?? 0, 0, '.', ' ') . ' ¥';
                                                
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
                        ->label(__('Quantité'))
                                                        ->numeric()
                                                        ->default(1)
                                                        ->minValue(1)
                                                        ->required(fn (Get $get) => (bool) $get("opt_enabled_{$optId}"))
                                                        ->visible(fn (Get $get) => (bool) $get("opt_enabled_{$optId}"))
                                                        ->live();

                                                    $optionalSchema[] = Group::make()->schema([$toggle, $qtyInput])->columns(2);
                                                } else {
                                                    $optionalSchema[] = $toggle;
                                                    $optionalSchema[] = Hidden::make("opt_qty_{$optId}");
                                                }
                                            }

            $schema[] = Section::make(__('Options tarifaires complémentaires'))
                    ->description(__('Cochez les options souhaitées selon leur mode de facturation.'))
                                                ->schema($optionalSchema);
                                        }

                                        return $schema;
                                    }),

                                Group::make()
                                    ->visible(function (Get $get, $livewire) {
                                        $productId = $get('product_id')
                                                  ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                  ?? data_get($livewire, 'mountedActionData.product_id');
                                        if (!$productId) return false;
                                        $p = \App\Models\Product::find($productId);
                                        return (!$p || $p->product_type !== 'transport');
                                    })
                                    ->schema(function (Get $get, $livewire) {
                                        $productId = $get('product_id')
                                                  ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                  ?? data_get($livewire, 'mountedActionData.product_id');
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
                                                        'file' => self::storeServiceFileOnGoogleDrive(\Filament\Forms\Components\FileUpload::make($formKey)
                                                            ->label($label)
                                                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                                            ->maxSize(10240)
                                                            ->preserveFilenames()
                                                            ->downloadable()
                                                            ->openable()),
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
                                                    'file' => self::storeServiceFileOnGoogleDrive(\Filament\Forms\Components\FileUpload::make($formKey)
                                                        ->label($label)
                                                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                                        ->maxSize(10240)
                                                        ->preserveFilenames()
                                                        ->downloadable()
                                                        ->openable()),
                                                    default => TextInput::make($formKey)->label($label),
                                                };
                                                if ($isRequired) $field->required();
                                                $fields[] = $field;
                                            }
                                        }
                                        
                                        if (empty($fields)) return [];

                                        return [
            Section::make(__('Informations requises par le fournisseur'))
                                                ->schema($fields)->columns(2)
                                        ];
                                    }),

                                Group::make()
                                    ->visible(function (Get $get, $livewire) {
                                        $productId = $get('product_id')
                                                  ?? data_get($livewire, 'mountedTableActionData.product_id')
                                                  ?? data_get($livewire, 'mountedActionData.product_id');
                                        if (!$productId) return false;
                                        $p = \App\Models\Product::find($productId);
                                        return (!$p || $p->product_type !== 'transport') && !empty($get('service_date'));
                                    })
                                    ->schema([
                                        Placeholder::make('estimated_price_display')
                                            ->hiddenLabel()
                                            ->content(function (Get $get) {
                                                $productId = $get('product_id');
                                                $date = $get('service_date');
                                                $qty = (int) ($get('quantity') ?? 1);
                                                
                                                if (!$productId || !$date) return new HtmlString('<span class="text-gray-500">' . e(__('Sélectionnez une date pour estimer le prix.')) . '</span>');
                                                
                                                $product = \App\Models\Product::with(['productPeriods.productPrices', 'productOptions'])->find($productId);
                                                if (!$product) return 'Produit introuvable.';
                                                
                                                $mdStr = \Carbon\Carbon::parse($date)->format('m-d');
                                                $matchedPrice = null;
                                                $ageBaseTotal = null;
                                                $ageAdults = max(0, (int) ($get('age_selector_adults') ?? 0));
                                                $ageChildren = max(0, (int) ($get('age_selector_children') ?? 0));
                                                $ageTeenagers = max(0, (int) ($get('age_selector_teenagers') ?? 0));
                                                if (($ageAdults + $ageChildren + $ageTeenagers) === 0) {
                                                    $ageAdults = $qty;
                                                }
                                                if ($product->productPeriods) {
                                                    foreach ($product->productPeriods as $period) {
                                                        $inPeriod = true; // Par défaut : Toute l'année
                                                        
                                                        if ($period->start_date && $period->end_date) {
                                                            $inPeriod = ($period->start_date <= $period->end_date) 
                                                                ? ($mdStr >= $period->start_date && $mdStr <= $period->end_date)
                                                                : ($mdStr >= $period->start_date || $mdStr <= $period->end_date);
                                                        }
                                                        
                                                        if ($inPeriod && $period->productPrices) {
                                                            $validPrices = $period->productPrices->where('min_pax', '<=', $qty)->where('max_pax', '>=', $qty);
                                                            if ($validPrices->isNotEmpty()) {
                                                                $matchedPrice = $validPrices->first()->price;
                                                                if ($product->enable_age_selector) {
                                                                    $ageBaseTotal = $product->calculateAgeBasedBaseTotal(
                                                                        $period,
                                                                        $qty,
                                                                        $ageAdults,
                                                                        $ageChildren,
                                                                        $ageTeenagers,
                                                                    );
                                                                }
                                                            } else {
                                                                $matchedPrice = $period->productPrices->sortByDesc('max_pax')->first()->price ?? 0;
                                                            }
                                                            break;
                                                        }
                                                    }
                                                }
                                                
                                                $baseUnitPrice = $matchedPrice ?? 0;
                                                $baseTotal = $ageBaseTotal !== null
                                                    ? $ageBaseTotal
                                                    : ($baseUnitPrice * $qty);
                                                if ($ageBaseTotal !== null) {
                                                    $baseUnitPrice = $ageBaseTotal / max(1, $qty);
                                                }
                                                
                                                $optionsTotal = 0;
                                                $optRows = [];
                                                if ($product->productOptions) {
                                                    foreach ($product->productOptions as $opt) {
                                                        if ($get("opt_enabled_{$opt->id}")) {
                                                            $mod = $opt->price_modifier ?? 0;
                                                            if ($opt->billing_type === 'per_pax') {
                                                                if ($product->enable_age_selector) {
                                                                    $adultMod = $opt->priceModifierForAge(false);
                                                                    $childMod = $opt->priceModifierForAge(true);
                                                                    $teenMod = $opt->priceModifierForAgeGroup('teen');
                                                                    $optTotal = ($adultMod * $ageAdults) + ($childMod * $ageChildren) + ($teenMod * $ageTeenagers);
                                                                    $calcText = number_format($adultMod, 0, '.', ' ') . " ¥ × {$ageAdults} adulte(s) + " . number_format($childMod, 0, '.', ' ') . " ¥ × {$ageChildren} enfant(s)" . ($product->teen_age_limit !== null ? " + " . number_format($teenMod, 0, '.', ' ') . " ¥ × {$ageTeenagers} adolescent(s)" : '');
                                                                } else {
                                                                    $optTotal = $mod * $qty;
                                                                    $calcText = number_format($mod, 0, '.', ' ') . " ¥ × {$qty} pax";
                                                                }
                                                            } elseif ($opt->billing_type === 'per_booking') {
                                                                $optTotal = $mod;
                                                                $calcText = "forfait fixe";
                                                            } elseif ($opt->billing_type === 'manual') {
                                                                $optQty = (int) ($get("opt_qty_{$opt->id}") ?? 1);
                                                                $optTotal = $mod * $optQty;
                                                                $calcText = number_format($mod, 0, '.', ' ') . " ¥ × {$optQty}";
                                                            }
                                                            $optionsTotal += $optTotal;
                                                            $optRows[] = "
                                                                <div style='display:flex; justify-content:space-between; color:#475569; padding-left:12px; margin-top:2px; font-size:0.825rem;'>
                                                                    <span>└ <i>Option : " . e($opt->name) . "</i> ({$calcText})</span>
                                                                    <span style='color:#0284c7; font-weight:600;'>+ " . number_format($optTotal, 0, '.', ' ') . " ¥</span>
                                                                </div>
                                                            ";
                                                        }
                                                    }
                                                }
                                                
                                                $grandTotal = $baseTotal + $optionsTotal;
                                                
                                                return new HtmlString("
                                                    <div style='padding: 1rem; border-radius: 0.5rem; background-color: #f0fdf4; border: 1px solid #bbf7d0; margin-top: 1rem;'>
                                                        <div style='font-weight:600; color:#166534; font-size:0.9rem; margin-bottom:0.35rem;'>Calcul estimatif du prix :</div>
                                                        <div style='display:flex; justify-content:space-between; font-size:0.875rem; color:#15803d;'>
                                                            <span>• Tarif de base / Frais de service (" . number_format($baseUnitPrice, 0, '.', ' ') . " ¥ × {$qty} pax)</span>
                                                            <span><b>" . number_format($baseTotal, 0, '.', ' ') . " ¥</b></span>
                                                        </div>
                                                        " . implode('', $optRows) . "
                                                        <div style='border-top:1px solid #86efac; margin-top:0.5rem; padding-top:0.35rem; display:flex; justify-content:space-between; align-items:center;'>
                                                            <span style='font-weight:bold; color:#166534;'>" . e(__('Prix total estimé :')) . "</span>
                                                            <span style='font-size:1.35rem; font-weight:bold; color:#15803d;'>" . number_format($grandTotal, 0, '.', ' ') . " ¥</span>
                                                        </div>
                                                    </div>
                                                ");
                                            })
                                    ]),
                            ])
                            ->action(function (array $data, ?Model $record, $component) {
                                if (!$record) return;

                                $rawInput = data_get($component->getLivewire(), 'mountedTableActionData') 
                                         ?? data_get($component->getLivewire(), 'mountedActionData') 
                                         ?? [];
                                $mergedData = array_merge((array)$rawInput, (array)$data);

                                $status = \App\Models\ItemStatus::firstOrCreate(
                                    ['name' => 'En attente de validation'],
                                    ['color' => 'warning']
                                );

                                $product = \App\Models\Product::find($mergedData['product_id'] ?? null);

                                if (! $product || ! $product->isAvailableForAgency($record->agency_id)) {
                                    Notification::make()
                                        ->title(__('Prestation non disponible'))
                                        ->body(__('Cette prestation n’est pas accessible au groupe de votre agence.'))
                                        ->danger()
                                        ->send();

                                    return;
                                }
                                $customValues = [];
                                $paxAdults = null;
                                $paxChildren = null;
                                $paxTeenagers = null;

                                if ($product && $product->product_type === 'transport') {
                                    $customValues['transport_routes'] = $mergedData['transport_routes'] ?? [];
                                    $firstRoute = $mergedData['transport_routes'][0] ?? null;
                                    $serviceDate = !empty($firstRoute['departure_date']) ? $firstRoute['departure_date'] : ($record->start_date ?? now()->format('Y-m-d'));
                                    $quantity = !empty($firstRoute['pax_count']) ? (int)$firstRoute['pax_count'] : 1;
                                    $formattedOptions = [];
                                } else {
                                    $serviceDate = $mergedData['service_date'] ?? $record->start_date ?? now()->format('Y-m-d');
                                    $quantity = $mergedData['quantity'] ?? 1;

                                    if ($product?->usesAgeSelector()) {
                                        $adults = max(0, (int) ($mergedData['age_selector_adults']
                                            ?? data_get($mergedData, 'custom_values._age_selector.adults', 0)));
                                        $children = $product->hasChildSelector()
                                            ? max(0, (int) ($mergedData['age_selector_children']
                                                ?? data_get($mergedData, 'custom_values._age_selector.children', 0)))
                                            : 0;
                                        $teenagers = $product->hasTeenSelector()
                                            ? max(0, (int) ($mergedData['age_selector_teenagers']
                                                ?? data_get($mergedData, 'custom_values._age_selector.teenagers', 0)))
                                            : 0;

                                        if (($adults + $children + $teenagers) === 0) {
                                            $adults = max(1, (int) $quantity);
                                        }

                                        $quantity = $adults + $children + $teenagers;
                                        $paxAdults = $adults;
                                        $paxChildren = $children;
                                        $paxTeenagers = $teenagers;
                                        $customValues['_age_selector'] = [
                                            'adults' => $adults,
                                            'children' => $children,
                                            'teenagers' => $teenagers,
                                            'total' => $quantity,
                                            'child_age_limit' => (int) ($product->child_age_limit ?? 11),
                                            'teen_age_limit' => $product->teen_age_limit ? (int) $product->teen_age_limit : null,
                                        ];
                                    }

                                    $formattedOptions = [];
                                    
                                    if ($product) {
                                        $productOptions = \App\Models\ProductOption::where('product_id', $product->id)->get();
                                        
                                        // 1. Grouped / Required Options (Selects)
                                        $requiredGrouped = $productOptions
                                            ->filter(fn($o) => $o->is_required || !empty($o->group_name))
                                            ->groupBy(fn($o) => !empty($o->group_name) ? $o->group_name : 'default_required');

                                        foreach ($requiredGrouped as $groupName => $groupOpts) {
                                            $slug = Str::slug($groupName);
                                            $selectKey = "opt_group_{$slug}";
                                            
                                            $selectedOptId = $mergedData[$selectKey] ?? null;
                                            if (!$selectedOptId && $groupOpts->isNotEmpty()) {
                                                $selectedOptId = $groupOpts->first()->id;
                                            }

                                            if ($selectedOptId) {
                                                $formattedOptions[] = [
                                                    'product_option_id' => (int) $selectedOptId,
                                                    'quantity' => 1,
                                                ];
                                            }
                                        }

                                        // 2. Optional Toggles (Checkboxes)
                                        $optionalOpts = $productOptions->filter(fn($o) => !$o->is_required && empty($o->group_name));

                                        foreach ($optionalOpts as $opt) {
                                            $toggleKey = "opt_enabled_{$opt->id}";
                                            if (!empty($mergedData[$toggleKey])) {
                                                $qtyKey = "opt_qty_{$opt->id}";
                                                $qty = (int) ($mergedData[$qtyKey] ?? 1);
                                                $formattedOptions[] = [
                                                    'product_option_id' => $opt->id,
                                                    'quantity' => max(1, $qty),
                                                ];
                                            }
                                        }
                                    }

                                    if ($product && !empty($product->custom_field_definitions)) {
                                        foreach ($product->custom_field_definitions as $def) {
                                            $key = !empty($def['key']) ? $def['key'] : Str::slug($def['name'] ?? 'custom', '_');
                                            $isPerPax = $def['is_per_passenger'] ?? false;
                                            $paxCount = max(1, (int) ($mergedData['quantity'] ?? 1));

                                            if ($isPerPax) {
                                                $paxVals = [];
                                                for ($i = 1; $i <= $paxCount; $i++) {
                                                    $fk = "custom_{$key}_pax_{$i}";
                                                    if (isset($mergedData[$fk]) && $mergedData[$fk] !== '') {
                                                        $v = $mergedData[$fk];
                                                        if (is_bool($v)) $v = $v ? 'Oui' : 'Non';
                                                        $paxVals[] = "Pax {$i}: {$v}";
                                                    }
                                                }
                                                if (!empty($paxVals)) {
                                                    $customValues[$key] = implode("\n", $paxVals);
                                                }
                                            } else {
                                                $fk = "custom_{$key}";
                                                if (isset($mergedData[$fk])) {
                                                    $customValues[$key] = $mergedData[$fk];
                                                }
                                            }
                                        }
                                    }
                                }

                                $lateRequestDate = null;

                                if ($product) {
                                    $requestDates = $product->isTransport()
                                        ? array_column($customValues['transport_routes'] ?? [], 'departure_date')
                                        : [$serviceDate];

                                    foreach ($requestDates as $requestDate) {
                                        if ($requestDate && $product->isAgencyRequestDeadlineExceeded($requestDate)) {
                                            $lateRequestDate = $requestDate;
                                            break;
                                        }
                                    }
                                }

                                if ($lateRequestDate) {
                                    $deadline = $product->getAgencyRequestDeadline($lateRequestDate);

                                    \Filament\Notifications\Notification::make()
                                        ->title(__('Délai de demande dépassé'))
                                        ->body(__('Cette prestation devait être demandée au plus tard le :date. Contactez-nous via le formulaire pour connaître les alternatives encore possibles.', [
                                            'date' => $deadline?->format('d/m/Y'),
                                        ]))
                                        ->danger()
                                        ->persistent()
                                        ->actions([
                                            Action::make('contact')
                                                ->label(__('Nous contacter'))
                                                ->url(route('filament.agency.pages.assistance-sur-mesure')),
                                        ])
                                        ->send();

                                    return;
                                }

                                $folderItem = \App\Models\FolderItem::create([
                                    'folder_id' => $record->id,
                                    'product_id' => $mergedData['product_id'],
                                    'service_date' => $serviceDate,
                                    'quantity' => $quantity,
                                    'pax_adults' => $paxAdults,
                                    'pax_children' => $paxChildren,
                                    'pax_teenagers' => $paxTeenagers,
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
                ->title(__('Prestation ajoutée au dossier !'))
                                    ->body(__('La prestation a été enregistrée.'))
                                    ->success()
                                    ->send();

                                return redirect(request()->header('Referer'));
                            })
                    ])
                    ->schema([
                        Repeater::make('folderItems')
                            ->relationship()
                            ->saveRelationshipsUsing(null)
                            ->dehydrated(false)
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->extraItemActions([
                                Action::make('deleteItem')
                    ->label(__('Supprimer'))
                                    ->icon('heroicon-m-trash')
                                    ->color('danger')
                                    ->requiresConfirmation()
                    ->modalHeading(__('Supprimer cette prestation ?'))
                                    ->modalSubheading('Cette action retirera la prestation de votre dossier.')
                                    ->visible(fn ($record, Get $get) => ($record?->status === 'draft') || ($get('../../status') === 'draft'))
                                    ->action(function (array $arguments, Repeater $component) {
                                        $state = $component->getState();
                                        $itemData = $state[$arguments['item']] ?? [];
                                        if (!empty($itemData['id'])) {
                                            $item = \App\Models\FolderItem::find($itemData['id']);
                                            if ($item) {
                                                $folder = $item->folder;
                                                $item->delete();
                                                if ($folder) {
                                                    $record = $folder->fresh();
                                                    $totalSale = $record->folderItems->sum('total_price') + ($record->folder_fee ?? 0);
                                                    $record->update(['total_price' => $totalSale]);
                                                }
                                            }
                                        }
                                    }),
                            ])
                            ->itemLabel(function (array $state) {
                                return new HtmlString("<span style='color:#096a61; font-weight:600;'>" . e(__('Prestation enregistrée')) . "</span>");
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

                                        if ($item->product && $item->product->product_type === 'transport') {
                                            $routesHtml = '';
                                            $customVals = $item->custom_values ?? [];
                                            if (is_array($customVals) && isset($customVals['transport_routes']) && is_array($customVals['transport_routes'])) {
                                                $routeLines = [];
                                                foreach ($customVals['transport_routes'] as $idx => $r) {
                                                    $dep = e($r['departure_station'] ?? 'Inconnu');
                                                    $arr = e($r['arrival_station'] ?? 'Inconnu');
                                                    $rDate = !empty($r['departure_date']) ? \Carbon\Carbon::parse($r['departure_date'])->format('d/m/Y') : '---';
                                                    $rPax = !empty($r['pax_count']) ? $r['pax_count'] . ' pax' : '1 pax';

                                                    // Horaires
                                                    $timeDisplay = '';
                                                    if (!empty($r['departure_time']) && !empty($r['arrival_time'])) {
                                                        $timeDisplay = " | 🕒 " . e($r['departure_time']) . " ➔ " . e($r['arrival_time']);
                                                    } elseif (!empty($r['departure_time'])) {
                                                        $timeDisplay = " | 🕒 " . e($r['departure_time']);
                                                    }

                                                    // Numéro de train/bus
                                                    $trainNum = !empty($r['train_number']) ? " | 🎫 " . e($r['train_number']) : '';

                                                    // Option / Classe
                                                    $optName = '';
                                                    if (!empty($r['option_id'])) {
                                                        $optModel = \App\Models\ProductOption::find($r['option_id']);
                                                        if ($optModel) $optName = " | 💺 " . e($optModel->name);
                                                    }

                                                    $num = $idx + 1;
                                                    $routeLines[] = "<div style='margin-bottom: 0.6rem;'>
                                                        <span style='font-weight:700;'>• Trajet {$num} : {$dep} ➔ {$arr}</span><br>
                                                        <span style='margin-left: 0.8rem; font-size: 0.8rem; opacity: 0.85;'>📅 {$rDate}{$timeDisplay}{$trainNum} | 👥 {$rPax}{$optName}</span>
                                                    </div>";
                                                }
                                                $routesHtml = "
                                                    <div style='margin-top:0.5rem; padding:0.75rem; padding-bottom: 0.15rem; background:#f0f9ff; border-radius:0.5rem; border:1px solid #bae6fd; font-size:0.85rem; color:#0369a1; line-height:1.4;'>
                                                        " . implode('', $routeLines) . "
                                                    </div>
                                                ";
                                            }

                                            // Le prix reste provisoire uniquement tant que la prestation
                                            // n'a pas été prise en charge par l'équipe Takada.
                                            $isAwaitingValidation = $statusName === 'En attente de validation';
                                            $ticketCost = max(0, (float) ($item->purchase_total_price ?? 0));
                                            $bookingFees = max(0, (float) $item->total_price - $ticketCost);

                                            $priceDetailsHtml = $isAwaitingValidation
                                                ? ''
                                                : "<div style='display:flex; flex-direction:column; gap:0.35rem; margin-top:0.5rem; padding:0.75rem; background:#f8fafc; border-radius:0.5rem; font-size:0.85rem;'>
                                                    <div style='display:flex; justify-content:space-between; gap:1rem; color:#475569;'>
                                                        <span>" . e(__('Frais d’émission / réservation')) . "</span>
                                                        <span style='font-weight:700; color:#0f172a; white-space:nowrap;'>" . number_format($bookingFees, 0, '.', ' ') . " ¥</span>
                                                    </div>
                                                    <div style='display:flex; justify-content:space-between; gap:1rem; color:#475569;'>
                                                        <span>" . e(__('Coût des billets')) . "</span>
                                                        <span style='font-weight:700; color:#0f172a; white-space:nowrap;'>" . number_format($ticketCost, 0, '.', ' ') . " ¥</span>
                                                    </div>
                                                </div>";

                                            $prixSidenote = $isAwaitingValidation
                                                ? "<span style='font-size:0.85rem; color:#64748b; font-style:italic;'>" . e(__('Prix définitif communiqué après confirmation')) . "</span>"
                                                : "<span style='font-size:0.85rem; color:#64748b; font-weight:600;'>" . e(__('Prix total')) . "</span>";

                                            return new HtmlString("
                                                <div style='display:flex; flex-direction:column; gap:0.5rem; font-family:system-ui, sans-serif;'>
                                                    <div style='display:flex; justify-content:space-between; align-items:flex-start;'>
                                                        <div>
                                                            <div style='font-size:1.1rem; font-weight:800; color:#0f172a;'>🚄 {$productName}</div>
                                                            <div style='font-size:0.85rem; color:#64748b; font-weight:500;'>Réservation Billet sur devis</div>
                                                        </div>
                                                        <div>{$statusBadge}</div>
                                                    </div>
                                                    {$routesHtml}
                                                    {$priceDetailsHtml}
                                                    <div style='display:flex; justify-content:space-between; align-items:center; margin-top:0.5rem; padding-top:0.5rem; border-top:1px dashed #cbd5e1;'>
                                                        {$prixSidenote}
                                                        <span style='font-size:1.1rem; font-weight:800; color:#096a61;'>" . number_format($item->total_price, 0, '.', ' ') . " ¥</span>
                                                    </div>
                                                </div>
                                            ");
                                        }

                                        // Décomposition tarifaire standard
                                        $optionsTotal = 0;
                                        $optRows = [];
                                        
                                        // Filament cast $item->selected_options en array nativement grâce au Model FolderItem. 
                                        // On s'assure juste d'avoir un array.
                                        $rawOptions = $item->selected_options ?? [];
                                        $selectedOptions = is_string($rawOptions) ? json_decode($rawOptions, true) : (is_array($rawOptions) ? $rawOptions : []);
                                        $ageMeta = is_array($item->custom_values['_age_selector'] ?? null) ? $item->custom_values['_age_selector'] : null;
                                        $ageAdults = max(0, (int) ($ageMeta['adults'] ?? 0));
                                        $ageChildren = max(0, (int) ($ageMeta['children'] ?? 0));
                                        $ageTeenagers = max(0, (int) ($ageMeta['teenagers'] ?? 0));

                                        if (!empty($selectedOptions)) {
                                            foreach ($selectedOptions as $optData) {
                                                // Le select stocke parfois juste l'ID sous forme de chaîne, ou un array avec "product_option_id"
                                                $optId = is_array($optData) ? ($optData['product_option_id'] ?? null) : $optData;
                                                
                                                if (!empty($optId)) {
                                                    $optModel = \App\Models\ProductOption::find($optId);
                                                    if ($optModel) {
                                                        $mod = (float) ($optModel->price_modifier ?? 0);
                                                        $optQty = is_array($optData) ? (int) ($optData['quantity'] ?? 1) : 1;

                                                        if ($optModel->billing_type === 'per_pax') {
                                                            if ($item->product?->enable_age_selector && ($ageAdults + $ageChildren + $ageTeenagers) > 0) {
                                                                $adultMod = $optModel->priceModifierForAge(false);
                                                                $childMod = $optModel->priceModifierForAge(true);
                                                                $teenMod = $optModel->priceModifierForAgeGroup('teen');
                                                                $lineTotal = ($adultMod * $ageAdults) + ($childMod * $ageChildren) + ($teenMod * $ageTeenagers);
                                                                $teenText = $item->product?->teen_age_limit !== null ? " + {$teenMod}¥ x {$ageTeenagers} adolescents" : '';
                                                                $optRows[] = "{$optModel->name} (+{$adultMod}¥ x {$ageAdults} adultes + {$childMod}¥ x {$ageChildren} enfants{$teenText} = {$lineTotal}¥)";
                                                            } else {
                                                                $lineTotal = $mod * $qty;
                                                                $optRows[] = "{$optModel->name} (+{$mod}¥ x {$qty} pax = {$lineTotal}¥)";
                                                            }
                                                            $optionsTotal += $lineTotal;
                                                        } elseif ($optModel->billing_type === 'manual') {
                                                            $lineTotal = $mod * $optQty;
                                                            $optRows[] = "{$optModel->name} (+{$mod}¥ x {$optQty} = {$lineTotal}¥)";
                                                            $optionsTotal += $lineTotal;
                                                        } else {
                                                            $optRows[] = "{$optModel->name} (Prix fixe +{$mod}¥)";
                                                            $optionsTotal += $mod;
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        $baseUnitPrice = (float) $item->unit_price - ($optionsTotal / max(1, $qty)); 
                                        $baseTotalPrice = $baseUnitPrice * $qty;
                                        $grandTotal = $item->total_price;

                                        $optionsHtml = '';
                                        if (count($optRows) > 0) {
                                            $list = implode('<br>', array_map(fn($r) => "• $r", $optRows));
                                            $optionsHtml = "
                                                <div style='margin-top:0.75rem; padding:0.75rem; background:#f8fafc; border-radius:0.5rem; border:1px solid #e2e8f0;'>
                                                    <div style='font-size:0.75rem; font-weight:700; color:#475569; margin-bottom:0.25rem; text-transform:uppercase;'>Déclinaisons & Options sélectionnées :</div>
                                                    <div style='font-size:0.8rem; color:#334155; line-height:1.4;'>{$list}</div>
                                                </div>
                                            ";
                                        }

                                        return new HtmlString("
                                            <div style='display:flex; flex-direction:column; gap:0.5rem; font-family:system-ui, sans-serif;'>
                                                <div style='display:flex; justify-content:space-between; align-items:flex-start;'>
                                                    <div>
                                                        <div style='font-size:1.1rem; font-weight:800; color:#0f172a; margin-bottom:0.25rem;'>{$productName}</div>
                                                        <div style='font-size:0.85rem; color:#64748b; font-weight:500; display:flex; gap:1rem;'>
                                                            <span>📅 {$date}</span>
                                                            <span>👥 {$qty} Pax</span>
                                                        </div>
                                                    </div>
                                                    <div style='text-align:right;'>
                                                        {$statusBadge}
                                                    </div>
                                                </div>

                                                {$optionsHtml}

                                                <div style='display:flex; justify-content:space-between; align-items:center; margin-top:0.5rem; padding-top:0.75rem; border-top:1px dashed #cbd5e1;'>
                                                    <div style='font-size:0.8rem; color:#64748b;'>
                                                        Base : " . number_format($baseTotalPrice, 0, '.', ' ') . " ¥ <br>
                                                        Options : " . number_format($optionsTotal, 0, '.', ' ') . " ¥
                                                    </div>
                                                    <div style='font-size:1.25rem; font-weight:800; color:#096a61;'>
                                                        " . number_format($grandTotal, 0, '.', ' ') . " ¥
                                                    </div>
                                                </div>
                                            </div>
                                        ");
                                    })
                            ])
                    ]),
            ])->columnSpan(['lg' => 2]),

            Group::make()->schema([
            Section::make(__('Récapitulatif & Statut'))
                    ->schema([
                        Placeholder::make('status_display')
                            ->label(__('Statut du dossier'))
                            ->content(function ($record) {
                                if (!$record || !$record->status) return 'BROUILLON (NOUVEAU)';
                                return match ($record->status) {
                                    'draft' => 'BROUILLON',
                                    'pending' => 'EN ATTENTE DE VALIDATION',
                                    'confirmed' => 'CONFIRMÉ / TRANSMIS',
                                    'completed' => 'VOYAGE TERMINÉ',
                                    'cancelled' => 'ANNULÉ',
                                    default => strtoupper($record->status),
                                };
                            })
                            ->extraAttributes(['class' => 'text-primary-600 font-bold']),

                        Placeholder::make('total_price_display')
                            ->label(__('Détail du montant estimé'))
                            ->content(function ($get) {
                                $items = $get('folderItems') ?? [];
                                $itemsTotal = array_sum(array_map(fn($i) => (float)($i['total_price'] ?? 0), $items));
                                
                                // 💡 Règle intelligente : si le dossier est un brouillon et que les frais sont à 0 (bug précédent), on récupère les frais du groupe client de l'agence.
                                $folderFee = (float) ($get('folder_fee') ?? 0);
                                if ($folderFee === 0.0 && $get('status') === 'draft') {
                                    $folderFee = (float) (\Filament\Facades\Filament::auth()->user()->agency?->clientGroup?->folder_fee ?? 0);
                                }

                                $grandTotal = $itemsTotal + $folderFee;

                                $itemsTotalFmt = number_format($itemsTotal, 0, '.', ' ') . ' ¥';
                                $folderFeeFmt = number_format($folderFee, 0, '.', ' ') . ' ¥';
                                $grandTotalFmt = number_format($grandTotal, 0, '.', ' ') . ' ¥';

                                return new \Illuminate\Support\HtmlString("
                                    <div style='display: flex; flex-direction: column; gap: 6px; font-size: 0.95rem; width: 100%; min-width: 250px;'>
                                        <div style='display: flex; justify-content: space-between; color: #475569;'>
                                            <span>Prestations :</span>
                                            <strong>{$itemsTotalFmt}</strong>
                                        </div>
                                        <div style='display: flex; justify-content: space-between; color: #475569; border-bottom: 1px solid #cbd5e1; padding-bottom: 6px; margin-bottom: 2px;'>
                                            <span>Frais de dossier :</span>
                                            <strong>{$folderFeeFmt}</strong>
                                        </div>
                                        <div style='display: flex; justify-content: space-between; font-size: 1.25rem; color: #1e3a8a; font-weight: bold;'>
                                            <span>TOTAL ESTIMÉ :</span>
                                            <span>{$grandTotalFmt}</span>
                                        </div>
                                    </div>
                                ");
                            }),
                            
                        Hidden::make('status')->default('draft'),
                        Hidden::make('total_price')->default(0),
                        Hidden::make('folder_fee')->default(fn () => \Filament\Facades\Filament::auth()->user()->agency?->clientGroup?->folder_fee ?? 0),
                        Hidden::make('agency_id')->default(fn () => \Filament\Facades\Filament::auth()->user()->agency_id),
                    ]),

            Section::make(__('Informations de Vol'))
                    ->schema([
                        Textarea::make('flight_info')
                            ->label(__('Vols (Arrivée/Départ)'))
                            ->placeholder(__('Ex: Vol AF276 Arrivée Haneda 10:30...'))
                            ->rows(3),
                    ]),

            Section::make(__('📂 Documents partagés'))
                    ->description(__('Fichiers mis à disposition par l\'équipe Takada.'))
                    ->schema([
                        Placeholder::make('documents_display')
                            ->hiddenLabel()
                            ->content(function (?Model $record) {
                                if (!$record || empty($record->documents) || !is_array($record->documents)) {
                                    return new HtmlString('<span style="color: #6b7280; font-style: italic; font-size: 0.875rem;">Aucun document partagé pour le moment.</span>');
                                }

                                $html = '<div style="display: flex; flex-direction: column; gap: 0.75rem;">';
                                foreach ($record->documents as $document) {
                                    $storage = app(FolderDriveStorage::class);
                                    $url = $storage->url($record, $document);
                                    $name = $storage->fileName($document);
                                    
                                    $html .= "
                                    <div style=\"display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.375rem;\">
                                        <div style=\"display: flex; align-items: center; gap: 0.75rem; overflow: hidden;\">
                                            <svg style=\"width: 1.25rem; height: 1.25rem; color: #9ca3af; flex-shrink: 0;\" xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"1.5\" stroke=\"currentColor\">
                                                <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z\" />
                                            </svg>
                                            <a href=\"{$url}\" target=\"_blank\" style=\"font-size: 0.875rem; font-weight: 500; color: #059669; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;\">
                                                {$name}
                                            </a>
                                        </div>
                                        <a href=\"{$url}\" download style=\"color: #6b7280; padding: 0.25rem; flex-shrink: 0;\">
                                            <svg style=\"width: 1.25rem; height: 1.25rem;\" xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"1.5\" stroke=\"currentColor\">
                                                <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3\" />
                                            </svg>
                                        </a>
                                    </div>";
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),

            ])->columnSpan(['lg' => 1]),

            Section::make(__('Communication avec l\'équipe Takada'))
                ->description(__('Échangez directement avec nous ici pour toute question ou envoi de fichier sur ce dossier.'))
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
                Tables\Columns\TextColumn::make('folder_name')
                    ->label(__('Dossier / Réf.'))
                    ->description(fn (Folder $record): string => __('Réf: :reference', ['reference' => $record->reference]))
                    ->searchable(['folder_name', 'reference'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('lead_traveler_name')
                    ->label(__('Pax Leader'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('mainSeller.name')
                    ->label(__('Vendeur'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('Arrivée'))
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Statut'))
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

                Tables\Columns\TextColumn::make('total_price')
                    ->label(__('Total Estimé'))
                    ->money('JPY')
                    ->getStateUsing(function ($record) {
                        $itemsTotal = $record->folderItems->sum('total_price');
                        $fee = (float) ($record->folder_fee ?? 0);
                        
                        // 💡 Le rattrapage intelligent pour les brouillons :
                        if ($fee === 0.0 && $record->status === 'draft') {
                            $fee = (float) ($record->agency?->clientGroup?->folder_fee ?? 0);
                        }
                        
                        return $itemsTotal + $fee;
                    })
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->label(__('Modifier'))
                    ->icon('heroicon-o-pencil-square'),

                Action::make('cancelDraftTable')
                    ->label(__('Annuler'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('Annuler et supprimer ce dossier brouillon ?'))
                    ->modalDescription(__('Ce dossier n\'a pas encore été transmis. Sa suppression entraînera le retrait définitif du brouillon.'))
                    ->modalSubmitActionLabel(__('Oui, supprimer le brouillon'))
                    ->modalCancelActionLabel(__('Conserver le brouillon'))
                    ->visible(fn ($record) => $record && $record->status === 'draft')
                    ->action(function ($record) {
                        $record->delete();

                        \Filament\Notifications\Notification::make()
                            ->title(__('Brouillon supprimé'))
                            ->body(__('Le dossier brouillon a été annulé avec succès.'))
                            ->success()
                            ->send();
                    }),
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
