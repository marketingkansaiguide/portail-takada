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

    /**
     * Duplique un séjour dans le formulaire : mêmes hôtel et chambres, arrivée = départ du séjour copié,
     * même nombre de nuits. La copie est ajoutée à l'état du formulaire (aucun enregistrement forcé, donc
     * aucune validation d'un autre onglet) et dépliée ; elle est enregistrée avec le reste du dossier.
     */
    public function duplicateStay(int $id): void
    {
        $source = $this->data['stays']['record-' . $id] ?? null;
        if (! is_array($source)) {
            return;
        }
        $in = filled($source['check_in'] ?? null) ? \Carbon\Carbon::parse($source['check_in'])->startOfDay() : null;
        $out = filled($source['check_out'] ?? null) ? \Carbon\Carbon::parse($source['check_out'])->startOfDay() : null;
        $nights = ($in && $out && $out->gte($in)) ? (int) round($in->floatDiffInDays($out)) : 1;
        $start = $out ?? $in;

        $copy = array_diff_key($source, array_flip(['id', 'folder_id', 'confirmation_ref', 'created_at', 'updated_at']));
        $copy['check_in'] = $start?->format('Y-m-d');
        $copy['check_out'] = $start?->copy()->addDays($nights)->format('Y-m-d');
        $copy['nights'] = $start ? $nights : null;
        $copy['status'] = 'to_book';

        $this->addStayToForm($copy);
    }

    /** Ajoute un séjour à compléter sur une plage de nuits manquantes (dans le formulaire, non enregistré). */
    public function addStayOnGap(string $from, int $nights): void
    {
        $in = \Carbon\Carbon::parse($from)->startOfDay();
        $this->addStayToForm([
            'hotel_name' => __('Hôtel à préciser'), 'check_in' => $in->format('Y-m-d'),
            'check_out' => $in->copy()->addDays(max(1, $nights))->format('Y-m-d'), 'nights' => max(1, $nights),
            'status' => 'to_book', 'booked_by' => 'takada',
        ]);
    }

    private function addStayToForm(array $stay): void
    {
        $key = (string) \Illuminate\Support\Str::uuid();
        $this->data['stays'][$key] = $stay;
        $this->js('window.tkOpenTarget(' . json_encode(__('Hébergements')) . ', ' . json_encode($key) . ')');
        \Filament\Notifications\Notification::make()->title(__('Séjour ajouté : complétez-le puis enregistrez.'))->success()->send();
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
