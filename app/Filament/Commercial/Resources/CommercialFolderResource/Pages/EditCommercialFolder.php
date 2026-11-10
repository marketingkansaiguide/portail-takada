<?php

namespace App\Filament\Commercial\Resources\CommercialFolderResource\Pages;

use App\Filament\Commercial\CommercialFolderForm;
use App\Filament\Commercial\Resources\CommercialFolderResource;
use App\Filament\Resources\Folders\Pages\EditFolder;
use Filament\Actions\Action;
use Filament\Schemas\Schema;

/**
 * Fiche dossier commerciale : onglets simplifiés, sans pré-facture, chat, fournisseurs ni
 * réglages de billetterie (toujours disponibles dans l'administration et pour la billetterie).
 */
class EditCommercialFolder extends EditFolder
{
    protected static string $resource = CommercialFolderResource::class;

    protected function usesRowSelection(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(CommercialFolderForm::components());
    }

    protected function getHeaderActions(): array
    {
        $kept = array_values(array_filter(
            parent::getHeaderActions(),
            fn ($action) => ! in_array($action->getName(), ['download_pdf', 'delete'], true),
        ));
        // La commande se déclenche depuis la barre de sélection de l'onglet Prestations ; l'action reste
        // montable (récapitulatif, verrou, idempotence) mais n'a plus de bouton dans l'en-tête.
        foreach ($kept as $action) {
            if ($action->getName() === 'orderPlannedItems') {
                $action->extraAttributes(['style' => 'display:none']);
            }
        }

        return [
            Action::make('saveTop')->label(__('Enregistrer'))->icon('heroicon-o-check')->action(fn () => $this->save()),
            ...$kept,
        ];
    }

    /** Pas de messagerie ni d'historique en tableau : l'historique est une section repliée de la fiche. */
    public function getRelationManagers(): array
    {
        return [];
    }
}
