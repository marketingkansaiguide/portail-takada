<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\CommercialFolderResource\Pages;
use App\Filament\Resources\Folders\FolderResource;

/**
 * Les dossiers dans l'espace commercial : même fiche, mêmes règles et même moteur
 * que l'administration (FolderResource), avec des adresses propres à cet espace.
 */
class CommercialFolderResource extends FolderResource
{
    protected static ?string $slug = 'dossiers';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('Dossiers');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Dossiers');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommercialFolders::route('/'),
            'create' => Pages\CreateCommercialFolder::route('/nouveau-dossier-agence'),
            'create-internal' => Pages\CreateCommercialInternalFolder::route('/nouveau'),
            'edit' => Pages\EditCommercialFolder::route('/{record}'),
        ];
    }
}
