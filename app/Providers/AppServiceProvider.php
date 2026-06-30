<?php

namespace App\Providers;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 🔒 1. PRINCIPAL : Popin sur toutes les suppressions individuelles (Pages et Tableaux)
        DeleteAction::configureUsing(function (DeleteAction $action) {
            $action
                ->requiresConfirmation()
                ->modalHeading(__('Confirmation de suppression'))
                ->modalDescription(__('Êtes-vous sûr de vouloir supprimer cet élément ? Cette action est définitive et irréversible.'))
                ->modalSubmitActionLabel(__('Supprimer'))
                ->modalCancelActionLabel(__('Annuler'))
                ->color('danger');
        });

        // 🔒 2. EN MASSE : Popin sur toutes les suppressions groupées (Bulk)
        DeleteBulkAction::configureUsing(function (DeleteBulkAction $action) {
            $action
                ->requiresConfirmation()
                ->modalHeading(__('Confirmation de suppression groupée'))
                ->modalDescription(__('Êtes-vous sûr de vouloir supprimer tous les éléments sélectionnés ? Cette action est définitive et irréversible.'))
                ->modalSubmitActionLabel(__('Tout supprimer'))
                ->modalCancelActionLabel(__('Annuler'))
                ->color('danger');
        });

        // 🔒 3. REPEATERS : Force la popin de confirmation sur TOUS les boutons corbeille des lignes imbriquées
        Repeater::configureUsing(function (Repeater $repeater) {
            $repeater->deleteAction(
                fn ($action) => $action
                    ->requiresConfirmation()
                    ->modalHeading(__('Retirer cette ligne'))
                    ->modalDescription(__('Êtes-vous sûr de vouloir retirer cet élément du dossier ?'))
                    ->modalSubmitActionLabel(__('Retirer'))
                    ->modalCancelActionLabel(__('Annuler'))
                    ->color('danger')
            );
        });
    }
}