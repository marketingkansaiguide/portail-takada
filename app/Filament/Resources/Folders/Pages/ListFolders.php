<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use App\Models\Folder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFolders extends ListRecords
{
    protected static string $resource = FolderResource::class;

    /**
     * Clé versionnée : les colonnes mémorisées en session avant la refonte de la liste
     * (Responsable, Segment, Avancement visibles) sont ignorées, la nouvelle disposition s'applique.
     */
    public function getTableColumnsSessionKey(): string
    {
        return parent::getTableColumnsSessionKey() . '_v2';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createInternal')
                ->label(__('Nouveau dossier interne'))
                ->icon('heroicon-o-briefcase')
                ->url(static::getResource()::getUrl('create-internal'))
                ->visible(fn () => auth('web')->user()?->can('createInternal', Folder::class)),

            CreateAction::make()
                ->label(__('Nouveau dossier agence (B2B)'))
                ->color('gray'),
        ];
    }
}
