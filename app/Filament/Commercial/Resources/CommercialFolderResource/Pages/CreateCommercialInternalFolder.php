<?php

namespace App\Filament\Commercial\Resources\CommercialFolderResource\Pages;

use App\Filament\Commercial\Resources\CommercialFolderResource;
use App\Filament\Resources\Folders\Pages\CreateInternalFolder;
use App\Models\Agency;
use App\Models\ClientGroup;
use App\Models\Folder;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Création en un écran : nom, dates, voyageurs, type de vente (facultatif), agence (information), commercial.
 * Plus de politique tarifaire (groupe client) : un dossier interne se prépare sans groupe. On arrive directement sur la fiche.
 */
class CreateCommercialInternalFolder extends CreateInternalFolder
{
    protected static string $resource = CommercialFolderResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('Nouveau voyage'))->columns(4)->schema([
                TextInput::make('folder_name')->label(__('Nom du voyage'))->placeholder(__('Ex : Famille Martin — Japon printemps 2027'))->required()->maxLength(255)->columnSpan(3),
                Select::make('kind')->label(__('Type de dossier'))->options(Folder::KINDS)->default(Folder::KIND_FULL_TRIP)->required()->native(false),
                DatePicker::make('start_date')->label(__('Début du séjour'))->native(false)->displayFormat('d/m/Y')->required(),
                DatePicker::make('end_date')->label(__('Fin du séjour'))->native(false)->displayFormat('d/m/Y')->required()->afterOrEqual('start_date'),
                TextInput::make('pax_adults')->label(__('Adultes'))->numeric()->minValue(0)->default(2)->required(),
                TextInput::make('pax_children')->label(__('Enfants'))->numeric()->minValue(0)->default(0)->required(),
                Select::make('sale_type')->label(__('Type de vente (facultatif)'))->options(Folder::SALE_TYPES)->placeholder(__('À préciser'))->native(false),
                Select::make('client_agency_id')->label(__('Agence (information)'))
                    ->helperText(__('Pour le suivi : ne donne aucun accès à l’agence.'))
                    ->relationship('clientAgency', 'name')->searchable()->preload()->placeholder(__('Aucune agence'))
                    ->createOptionForm([
                        TextInput::make('name')->label(__('Nom de l’agence'))->required()->maxLength(255),
                        TextInput::make('email')->label(__('E-mail'))->email()->required(),
                        Select::make('client_group_id')->label(__('Groupe client'))->options(fn () => ClientGroup::pluck('name', 'id'))->required(),
                    ])
                    ->createOptionUsing(fn (array $data): int => Agency::create($data + ['is_approved' => false])->getKey())
                    ->columnSpan(2),
                Select::make('owner_user_id')->label(__('Commercial'))
                    ->options(fn () => User::query()->where('role', '!=', User::ROLE_AGENCY)->orderBy('name')->get()->filter(fn (User $u) => $u->isStaff())->pluck('name', 'id'))
                    ->default(fn () => auth('web')->id())->searchable()->required()->columnSpan(2),
            ]),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['ops_status'] ??= 'brouillon';

        return parent::mutateFormDataBeforeCreate($data);
    }
}
