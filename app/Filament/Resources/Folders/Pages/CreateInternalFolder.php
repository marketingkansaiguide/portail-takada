<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use App\Filament\Resources\Folders\InternalFolderFields;
use App\Models\Folder;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Création rapide d'un dossier interne (commerciaux). Distincte de la création
 * d'un dossier agence : l'origine est fixée ici, côté serveur.
 */
class CreateInternalFolder extends CreateRecord
{
    protected static string $resource = FolderResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return __('Nouveau dossier interne');
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth('web')->user()?->can('createInternal', Folder::class), 403);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Dossier'))
                    ->columns(3)
                    ->schema(InternalFolderFields::components()),

                Section::make(__('Voyage (facultatif, à compléter plus tard)'))
                    ->columns(4)
                    ->collapsed()
                    ->schema([
                        DatePicker::make('start_date')->label(__('Arrivée au Japon'))->native(false),
                        DatePicker::make('end_date')->label(__('Départ'))->native(false)
                            ->afterOrEqual(fn ($get) => filled($get('start_date')) ? 'start_date' : null),
                        TextInput::make('pax_adults')->label(__('Adultes'))->numeric()->minValue(0)->default(0),
                        TextInput::make('pax_children')->label(__('Enfants'))->numeric()->minValue(0)->default(0),
                        TextInput::make('lead_traveler_name')->label(__('Voyageur principal'))->columnSpan(2),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['origin'] = Folder::ORIGIN_INTERNAL;
        $data['status'] = 'draft';
        $data['created_by_user_id'] = auth('web')->id();
        // Jamais d'agence propriétaire ni de vendeur agence sur un dossier interne.
        unset($data['agency_id'], $data['main_seller_id'], $data['segment']);

        return $data;
    }
}
