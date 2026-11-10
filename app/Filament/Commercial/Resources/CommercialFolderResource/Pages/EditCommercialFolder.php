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

    /** Duplique un séjour : mêmes hôtel et chambres, dates décalées à la suite (arrivée = départ du séjour copié). */
    public function duplicateStay(int $id): void
    {
        // Le formulaire est enregistré d’abord : le répéteur supprimerait sinon un séjour créé hors de lui.
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
        $stay = $this->getRecord()->stays()->whereKey($id)->first();
        if (! $stay) {
            return;
        }
        $nights = $stay->nights() ?? 1;
        $copy = $stay->replicate(['confirmation_ref']);
        $copy->check_in = $stay->check_out ?? $stay->check_in;
        $copy->check_out = $copy->check_in?->copy()->addDays($nights);
        $copy->status = 'to_book';
        $copy->save();
        $this->fillForm();
    }

    /** Ajoute un séjour à compléter sur une plage de nuits manquantes. */
    public function addStayOnGap(string $from, int $nights): void
    {
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
        $in = \Carbon\Carbon::parse($from);
        $this->getRecord()->stays()->create([
            'hotel_name' => __('Hôtel à préciser'), 'check_in' => $in, 'check_out' => $in->copy()->addDays(max(1, $nights)),
            'status' => 'to_book', 'booked_by' => 'takada',
        ]);
        $this->fillForm();
    }


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
