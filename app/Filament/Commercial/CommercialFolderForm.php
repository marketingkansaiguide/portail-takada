<?php

namespace App\Filament\Commercial;

use App\Filament\Resources\Folders\PlannedItemsSection;
use App\Models\Agency;
use App\Models\ClientGroup;
use App\Models\Folder;
use App\Models\FolderFlightSegment;
use App\Models\FolderStay;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Fiche dossier de l'espace commercial : sections et onglets pensés pour le travail quotidien.
 * Réutilise les modèles et le formulaire de prestation existants ; masque (sans supprimer)
 * ce qui relève de la billetterie, des fournisseurs et de l'administration.
 */
final class CommercialFolderForm
{
    /** @return array<int, mixed> */
    public static function components(): array
    {
        $view = fn (string $name) => View::make($name)->viewData(fn (?Folder $record) => ['folder' => $record])->visible(fn (?Folder $record) => (bool) $record)->columnSpanFull();

        return [
            Group::make([
            $view('filament.commercial.header'),

            // Sept onglets courts, sans icône : ils tiennent à 1280 px sans défilement horizontal.
            Tabs::make('commercial')
                ->persistTabInQueryString('onglet')
                ->extraAttributes(['class' => 'tk-c-tabs'])
                ->columnSpanFull()
                ->tabs([
                    Tab::make(__('Synthèse'))->schema([$view('filament.commercial.overview')]),
                    Tab::make(__('Voyageurs'))
                        ->badge(fn (?Folder $record) => $record?->folderPassengers()->count() ?: null)
                        ->schema([$view('filament.commercial.passengers-table')]),
                    Tab::make(__('Vols'))
                        ->badge(fn (?Folder $record) => $record?->flightSegments()->count() ?: null)
                        ->schema([$view('filament.commercial.flights-table')]),
                    Tab::make(__('Hôtels'))
                        ->badge(fn (?Folder $record) => $record?->stays()->count() ?: null)
                        ->schema([$view('filament.commercial.stays-table')]),
                    Tab::make(__('Prestations'))
                        ->badge(fn (?Folder $record) => $record?->plannedItems()->count() ?: null)
                        ->schema([
                            $view('filament.commercial.quick-add'),
                            $view('filament.commercial.planned-list'),
                            $view('filament.commercial.selection-bar'),
                            Section::make(__('Prestations commandées'))
                                ->description(__('Suivi des demandes transmises à la billetterie. La gestion des fournisseurs reste du ressort de la billetterie.'))
                                ->schema([$view('filament.commercial.ordered-items')])
                                ->visible(fn (?Folder $record) => (bool) $record),
                        ]),
                    Tab::make(__('Planning'))->schema([$view('filament.commercial.timeline')]),
                    Tab::make(__('Finances'))->schema([
                        Section::make(__('Montants'))->schema([$view('filament.commercial.finance')]),
                        $view('filament.commercial.note-link'),
                    ]),
                ]),
            ])->columnSpanFull()->extraAttributes(['class' => 'tk-c-shell']),

            Section::make(__('Historique des modifications'))
                ->description(__('Replié par défaut : qui a modifié quoi, et quand.'))
                ->collapsible()->collapsed()->persistCollapsed()
                ->columnSpanFull()
                ->visible(fn (?Folder $record) => (bool) $record)
                ->schema([$view('filament.commercial.history')]),
        ];
    }

    /**
     * Champs du dossier (identité) et consignes pour la billetterie : panneau « Modifier le dossier ».
     *
     * @return array<int, mixed>
     */
    public static function folderFields(): array
    {
        return [
            Section::make(__('Le dossier'))->columns(4)->schema([
                TextInput::make('folder_name')->label(__('Nom du dossier'))->required()->maxLength(255)->columnSpan(2),
                TextInput::make('quote_reference')->label(__('Référence du devis'))->maxLength(120)->columnSpan(1),
                Select::make('ops_status')->label(__('Statut du dossier'))->options(Folder::OPS_STATUSES)->required()->native(false)
                    ->helperText(__('Changer ce statut ne commande aucune prestation.'))->columnSpan(1),
                Select::make('sale_type')->label(__('Type de vente'))->options(Folder::SALE_TYPES)->placeholder(__('À préciser'))->native(false),
                Select::make('client_agency_id')
                    ->label(__('Agence partenaire (information)'))
                    ->helperText(__('Pour le suivi et les statistiques : ne donne aucun accès à l’agence.'))
                    ->relationship('clientAgency', 'name')
                    ->searchable()->preload()->placeholder(__('Aucune agence'))
                    ->createOptionForm([
                        TextInput::make('name')->label(__('Nom de l’agence'))->required()->maxLength(255),
                        TextInput::make('email')->label(__('E-mail'))->email()->required(),
                        Select::make('client_group_id')->label(__('Groupe client'))->options(fn () => ClientGroup::pluck('name', 'id'))->required(),
                    ])
                    ->createOptionUsing(fn (array $data): int => Agency::create($data + ['is_approved' => false])->getKey()),
                Select::make('owner_user_id')->label(__('Commercial responsable'))
                    ->options(fn () => User::query()->where('role', '!=', User::ROLE_AGENCY)->orderBy('name')->get()->filter(fn (User $u) => $u->isStaff())->pluck('name', 'id'))
                    ->searchable()->required(),
                Select::make('kind')->label(__('Type de dossier'))->options(Folder::KINDS)->required()->native(false),
                DatePicker::make('start_date')->label(__('Début du séjour'))->native(false)->displayFormat('d/m/Y')->required(),
                DatePicker::make('end_date')->label(__('Fin du séjour'))->native(false)->displayFormat('d/m/Y')->required()->afterOrEqual('start_date'),
                TextInput::make('pax_adults')->label(__('Adultes'))->numeric()->minValue(0)->default(1),
                TextInput::make('pax_children')->label(__('Enfants'))->numeric()->minValue(0)->default(0),
                TextInput::make('client_contact_name')->label(__('Client / contact principal'))->maxLength(255)->columnSpan(2),
                TextInput::make('client_contact_email')->label(__('E-mail du contact'))->email()->maxLength(255),
                TextInput::make('client_contact_phone')->label(__('Téléphone du contact'))->tel()->maxLength(60),
                Textarea::make('internal_notes')->label(__('Informations importantes sur le voyage'))->rows(3)->columnSpanFull(),
            ]),

            Section::make(__('Consignes générales pour la billetterie'))
                ->description(__('S’applique à tout le voyage. Distinct des informations destinées aux voyageurs. Aucun envoi automatique.'))
                ->schema([
                    Toggle::make('ticketing_urgent')->label(__('Consigne urgente'))->inline(false)->live(),
                    TextInput::make('ticketing_urgent_note')->label(__('Consigne urgente (texte court)'))->maxLength(500)
                        ->placeholder(__('Ex. : bus à réserver pour le 23/12.'))
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => (bool) $get('ticketing_urgent')),
                    Textarea::make('ticketing_general_notes')->hiddenLabel()->rows(4)
                        ->placeholder(__('Ex. : réserver le bus Nohi pour le 23/12.')),
                ]),
        ];
    }
    /** @return array<int, mixed> */
    public static function passengerFields(): array
    {
        return [
                Group::make()->columns(4)->schema([
                    TextInput::make('last_name')->label(__('Nom'))->required(),
                    TextInput::make('first_name')->label(__('Prénom'))->required(),
                    DatePicker::make('birth_date')->label(__('Date de naissance'))->native(false)->displayFormat('d/m/Y')->maxDate(now()),
                    TextInput::make('nationality')->label(__('Nationalité')),
                    TextInput::make('phone')->label(__('Téléphone'))->tel(),
                    TextInput::make('email')->label(__('E-mail'))->email(),
                    Toggle::make('is_lead')->label(__('Voyageur ou contact principal'))->inline(false)->columnSpan(2),
                ]),
                Group::make()->columns(3)->schema([
                    Textarea::make('dietary_restrictions')->label(__('Besoins alimentaires'))->rows(2),
                    Textarea::make('mobility_concerns')->label(__('Accessibilité / mobilité'))->rows(2),
                    Textarea::make('special_notes')->label(__('Informations particulières'))->rows(2),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    public static function flightFields(): array
    {
        $zones = fn () => collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn ($z) => [$z => $z])->all();

        return [
                Group::make()->columns(4)->schema([
                    Select::make('direction')->label(__('Sens'))->options(FolderFlightSegment::DIRECTIONS)->default('other')->native(false),
                    TextInput::make('airline')->label(__('Compagnie')),
                    TextInput::make('flight_number')->label(__('N° de vol'))->maxLength(20),
                    Placeholder::make('duration')->label(__('Durée'))->content(function (Get $get) {
                        $s = (new FolderFlightSegment)->forceFill([
                            'departure_local' => $get('departure_local'), 'arrival_local' => $get('arrival_local'),
                            'departure_tz' => $get('departure_tz'), 'arrival_tz' => $get('arrival_tz'),
                        ]);
                        $m = $s->durationMinutes();

                        return $m === null ? '—' : intdiv($m, 60) . 'h' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
                    }),
                ]),
                Group::make()->columns(3)->schema([
                    TextInput::make('departure_airport')->label(__('Aéroport de départ (code)'))->maxLength(60)->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, $state) => ($tz = FolderFlightSegment::timezoneFor($state)) ? $set('departure_tz', $tz) : null),
                    DateTimePicker::make('departure_local')->label(__('Départ (heure locale)'))->seconds(false)->native(false)->displayFormat('d/m/Y H:i')->live(),
                    Select::make('departure_tz')->label(__('Fuseau du départ'))->options($zones)->searchable()->live(),
                ]),
                Group::make()->columns(3)->schema([
                    TextInput::make('arrival_airport')->label(__('Aéroport d’arrivée (code)'))->maxLength(60)->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, $state) => ($tz = FolderFlightSegment::timezoneFor($state)) ? $set('arrival_tz', $tz) : null),
                    DateTimePicker::make('arrival_local')->label(__('Arrivée (heure locale)'))->seconds(false)->native(false)->displayFormat('d/m/Y H:i')->live(),
                    Select::make('arrival_tz')->label(__('Fuseau de l’arrivée'))->options($zones)->searchable()->live(),
                ]),
                Textarea::make('notes')->label(__('Informations complémentaires'))->rows(2),
        ];
    }

    /** @return array<int, mixed> */
    public static function stayFields(): array
    {
        return [
                Group::make()->columns(3)->schema([
                    Select::make('hotel_id')
                        ->label(__('Hôtel du référentiel'))
                        ->placeholder(__('Rechercher par nom, ville ou variante…'))
                        ->searchable()
                        ->searchDebounce(300)
                        ->getSearchResultsUsing(fn (string $search) => self::searchHotels($search))
                        ->getOptionLabelUsing(fn ($value) => ($h = Hotel::find($value)) ? self::hotelLabel($h) : null)
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            // Changer d'hôtel retire les catégories de l'ancien hôtel ; les libellés saisis à la main restent.
                            $lines = collect($get('room_lines') ?? []);
                            $kept = $lines->filter(fn ($l) => blank($l['room_type_id'] ?? null))->all();
                            $removed = $lines->count() - count($kept);
                            if ($removed > 0) {
                                $set('room_lines', $kept);
                                \Filament\Notifications\Notification::make()
                                    ->title(trans_choice(':count catégorie de chambre retirée|:count catégories de chambre retirées', $removed, ['count' => $removed]))
                                    ->body(__('Elles appartenaient à l’ancien hôtel : choisissez celles du nouvel hôtel.'))
                                    ->warning()->send();
                            }
                            if ($h = $state ? Hotel::find($state) : null) {
                                $set('hotel_name', $h->name);
                                $set('city', $h->city);
                                $set('address', $h->address);
                            }
                        }),
                    TextInput::make('hotel_name')->label(__('Nom de l’établissement'))->required()->maxLength(255)
                        ->helperText(__('Rempli par le référentiel ; à saisir ici si l’hôtel n’y figure pas.')),
                    TextInput::make('city')->label(__('Ville / destination')),
                ]),
                Group::make()->columns(4)->schema([
                    DatePicker::make('check_in')->label(__('Arrivée'))->native(false)->displayFormat('d/m/Y')->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::syncNights($get, $set, 'check_in')),
                    TextInput::make('nights')->label(__('Nuits'))->numeric()->minValue(1)->live(debounce: 400)->dehydrated(false)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::syncNights($get, $set, 'nights'))
                        ->helperText(__('Nuits ou départ : l’un calcule l’autre.')),
                    DatePicker::make('check_out')->label(__('Départ'))->native(false)->displayFormat('d/m/Y')->afterOrEqual('check_in')->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::syncNights($get, $set, 'check_out')),
                    Select::make('meal_plan')->label(__('Repas'))->options(FolderStay::MEAL_PLANS)->native(false),
                ]),
                Repeater::make('room_lines')
                    ->label(__('Chambres'))
                    ->addActionLabel(__('Ajouter une catégorie de chambre'))
                    ->helperText(__('Une ligne par catégorie : « quantité × catégorie ». Les catégories viennent de l’hôtel choisi ; vous pouvez aussi saisir un libellé.'))
                    ->defaultItems(0)->reorderable(false)->compact()->live()
                    ->schema([
                        Group::make()->columns(6)->schema([
                            TextInput::make('quantity')->label(__('Quantité'))->numeric()->minValue(1)->default(1)->required()->columnSpan(1),
                            Select::make('room_type_id')
                                ->label(__('Catégorie de l’hôtel'))
                                ->placeholder(fn (Get $get) => $get('../../hotel_id') ? __('Choisir…') : __('Choisissez d’abord un hôtel'))
                                ->options(fn (Get $get) => $get('../../hotel_id') ? HotelRoomType::where('hotel_id', $get('../../hotel_id'))->orderBy('name')->pluck('name', 'id')->all() : [])
                                ->searchable()->live()
                                ->afterStateUpdated(fn ($state, Set $set) => $state ? $set('label', HotelRoomType::find($state)?->name) : null)
                                ->columnSpan(2),
                            TextInput::make('label')->label(__('Libellé (personnalisable)'))->columnSpan(3),
                        ]),
                    ]),
                Select::make('copy_rooms_from')
                    ->label(__('Reprendre les chambres de…'))
                    ->placeholder(__('Un autre séjour du dossier'))
                    ->options(function (Get $get) {
                        return collect($get('other_stays') ?? [])
                            ->filter(fn ($s) => filled($s['room_lines'] ?? null) && filled($s['hotel_name'] ?? null))
                            ->mapWithKeys(fn ($s, $k) => [$k => ($s['hotel_name'] ?? '') . (filled($s['check_in'] ?? null) ? ' · ' . Carbon::parse($s['check_in'])->format('d/m') : '')])
                            ->all();
                    })
                    ->live()->dehydrated(false)
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        if (filled($state)) {
                            $set('room_lines', collect(($get('other_stays') ?? [])[$state]['room_lines'] ?? [])->map(fn ($l) => ['quantity' => $l['quantity'] ?? 1, 'room_type_id' => null, 'label' => $l['label'] ?? null])->values()->all());
                            $set('copy_rooms_from', null);
                        }
                    }),
                Group::make()->columns(4)->schema([
                    Select::make('room_category')->label(__('Type de chambre'))->options(FolderStay::ROOM_CATEGORIES)->native(false),
                ]),
                Group::make()->columns(4)->schema([
                    Select::make('status')->label(__('Statut'))->options(FolderStay::STATUSES)->default('to_book')->required()->native(false),
                    Select::make('booked_by')->label(__('Réservation faite par'))->options(FolderStay::BOOKED_BY)->default('takada')->required()->native(false)
                        ->helperText(__('Un hébergement réservé par le client n’est jamais à commander par Takada.')),
                    TextInput::make('confirmation_ref')->label(__('Référence de confirmation')),
                    Select::make('passenger_ids')->label(__('Voyageurs concernés'))->multiple()
                        ->options(fn (\Livewire\Component $livewire) => $livewire->getRecord()?->folderPassengers()->get()->mapWithKeys(fn ($p) => [$p->id => trim($p->last_name . ' ' . $p->first_name)])->all() ?? [])
                        ->placeholder(__('Tous')),
                ]),
                Textarea::make('address')->label(__('Adresse (facultative)'))->rows(1),
                Textarea::make('notes')->label(__('Notes'))->rows(2),
                Group::make()->columns(2)->schema([
                    TextInput::make('purchase_price')->label(__('Coût d’achat (¥, facultatif)'))->numeric()->minValue(0),
                    TextInput::make('sale_price')->label(__('Prix de vente (¥, facultatif)'))->numeric()->minValue(0),
                ]),
        ];
    }



    private static function nightsBetween(mixed $in, mixed $out): ?int
    {
        try {
            $a = $in ? Carbon::parse($in)->startOfDay() : null;
            $b = $out ? Carbon::parse($out)->startOfDay() : null;

            return ($a && $b && $b->gte($a)) ? (int) round($a->floatDiffInDays($b)) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Nuits ↔ départ : modifier l'un calcule l'autre. */
    private static function syncNights(Get $get, Set $set, string $changed): void
    {
        try {
            if ($changed === 'nights' && $get('check_in') && (int) $get('nights') > 0) {
                $set('check_out', Carbon::parse($get('check_in'))->addDays((int) $get('nights'))->format('Y-m-d'));
            } elseif ($get('check_in') && $get('check_out')) {
                $set('nights', self::nightsBetween($get('check_in'), $get('check_out')));
            } elseif ($changed === 'check_in' && $get('check_in') && (int) $get('nights') > 0) {
                $set('check_out', Carbon::parse($get('check_in'))->addDays((int) $get('nights'))->format('Y-m-d'));
            }
        } catch (\Throwable) {
        }
    }

    /** @return array<int|string, string> */
    public static function searchHotels(string $search): array
    {
        $term = '%' . trim(str_replace(['%', '_'], ['\%', '\_'], $search)) . '%';

        return Hotel::query()
            ->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('alt_names', 'like', $term)->orWhere('city', 'like', $term))
            ->orderBy('name')->limit(20)->get()
            ->mapWithKeys(fn (Hotel $h) => [$h->id => self::hotelLabel($h)])->all();
    }

    private static function hotelLabel(Hotel $h): string
    {
        return $h->name . ($h->city ? ' · ' . $h->city : '') . ($h->address ? ' (' . \Illuminate\Support\Str::limit($h->address, 40) . ')' : '');
    }
}
