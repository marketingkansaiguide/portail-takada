<?php

namespace App\Filament\Resources\Folders;

use App\Models\Agency;
use App\Models\ClientGroup;
use App\Models\Folder;
use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

/**
 * Champs propres aux dossiers internes (création rapide et fiche).
 * Seuls le type et l'intitulé sont obligatoires : un dossier se complète progressivement.
 */
final class InternalFolderFields
{
    /** @return array<int, mixed> */
    public static function components(): array
    {
        return [
            Select::make('kind')
                ->label(__('Type de dossier'))
                ->options(Folder::KINDS)
                ->default(Folder::KIND_FULL_TRIP)
                ->required()
                ->native(false),

            TextInput::make('folder_name')
                ->label(__('Intitulé'))
                ->placeholder(__('Ex : Famille Martin — Japon printemps 2027'))
                ->required()
                ->maxLength(255),

            Select::make('ops_status')
                ->label(__('Statut du dossier'))
                ->options(Folder::OPS_STATUSES)
                ->default('brouillon')
                ->required()
                ->native(false)
                ->helperText(__('Avancement commercial. Changer ce statut ne commande aucune prestation.')),

            Select::make('owner_user_id')
                ->label(__('Commercial responsable'))
                ->options(fn () => User::query()
                    ->where('role', '!=', User::ROLE_AGENCY)
                    ->orderBy('name')
                    ->get()
                    ->filter(fn (User $user) => $user->isStaff())
                    ->pluck('name', 'id'))
                ->default(fn () => auth('web')->id())
                ->searchable()
                ->required(),

            Select::make('client_agency_id')
                ->label(__('Agence associée (information)'))
                ->helperText(__('Pour le suivi et les statistiques. Ne donne aucun accès à l’agence et ne fixe pas les tarifs.'))
                ->relationship('clientAgency', 'name')
                ->searchable()
                ->preload()
                ->placeholder(__('Aucune agence'))
                ->createOptionForm([
                    TextInput::make('name')->label(__('Nom de l’agence'))->required()->maxLength(255),
                    TextInput::make('email')->label(__('Email'))->email()->required(),
                    Select::make('client_group_id')
                        ->label(__('Groupe client'))
                        ->options(fn () => ClientGroup::pluck('name', 'id'))
                        ->required(),
                    TextInput::make('phone')->label(__('Téléphone')),
                ])
                // Fiche agence seule : aucun compte utilisateur n'est créé.
                ->createOptionUsing(fn (array $data): int => Agency::create($data + ['is_approved' => false])->getKey()),

            TextInput::make('client_contact_name')->label(__('Client / contact principal'))->maxLength(255),
            TextInput::make('client_contact_email')->label(__('Email du contact'))->email()->maxLength(255),
            TextInput::make('client_contact_phone')->label(__('Téléphone du contact'))->tel()->maxLength(60),

            Select::make('sale_type')
                ->label(__('Type de vente'))
                ->options(Folder::SALE_TYPES)
                ->placeholder(__('À définir'))
                ->native(false),

            Select::make('pricing_client_group_id')
                ->label(__('Politique tarifaire (groupe client)'))
                ->options(fn () => ClientGroup::pluck('name', 'id'))
                ->placeholder(__('À définir'))
                ->helperText(__('Choisie explicitement : elle n’est jamais déduite de l’agence associée.')),

            Placeholder::make('pricing_warning')
                ->hiddenLabel()
                ->visible(fn ($get) => blank($get('sale_type')) || blank($get('pricing_client_group_id')))
                ->content(new HtmlString('<span style="color:#b45309;font-weight:600;">⚠ ' . e(__('Type de vente ou politique tarifaire non définis : les tarifs de ce dossier restent à confirmer.')) . '</span>'))
                ->columnSpanFull(),

            Textarea::make('internal_notes')
                ->label(__('Notes internes'))
                ->rows(4)
                ->columnSpanFull(),
        ];
    }

    public static function section(): Section
    {
        return Section::make(__('Dossier interne'))
            ->description(__('Dossier commercial Takada : jamais visible par une agence.'))
            ->extraAttributes(['data-tk-anchor' => 'informations'])
            ->visible(fn (?Folder $record): bool => (bool) $record?->isInternal())
            ->columns(3)
            ->schema(array_values(array_filter(
                self::components(),
                // L'intitulé est déjà présent dans « Informations générales ».
                fn ($component) => ! ($component instanceof TextInput && $component->getName() === 'folder_name')
            )));
    }
}
