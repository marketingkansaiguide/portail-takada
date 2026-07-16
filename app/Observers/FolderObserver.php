<?php

namespace App\Observers;

use App\Models\Folder;
use App\Models\User;
use Filament\Notifications\Notification;

class FolderObserver
{
    /**
     * RÈGLE 1 : Se déclenche quand un NOUVEAU dossier est créé
     */
    public function created(Folder $folder): void
    {
        // On récupère tous les utilisateurs de ton équipe (Admins et Super Admins)
        $admins = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->get();

        Notification::make()
            ->title('Nouveau dossier créé')
            ->body('Le dossier ' . $folder->reference . ' vient d\'être ajouté au système.')
            ->info() // Couleur bleue
            ->icon('heroicon-o-folder-plus')
            ->sendToDatabase($admins); // Envoie dans la cloche des admins
    }

    /**
     * RÈGLE 2 : Se déclenche quand un dossier EXISTANT est modifié
     */
    public function updated(Folder $folder): void
    {
        // 1. On vérifie SI la colonne "status" a été modifiée ET SI sa nouvelle valeur est "confirmed"
        if ($folder->wasChanged('status') && $folder->status === 'confirmed') {
            
            // Qui doit-on prévenir ?
            $notifiables = collect();

            // Si un agent (main_seller) est assigné à ce dossier, on le prévient lui en priorité
            if ($folder->main_seller_id) {
                $notifiables->push($folder->mainSeller);
            } else {
                // Sinon, on prévient tous les admins pour que quelqu'un prenne le relais
                $notifiables = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->get();
            }

            // On envoie la notification
            Notification::make()
                ->title('Dossier Confirmé ! 🎉')
                ->body('Le dossier ' . $folder->reference . ' est passé en statut Confirmé. Des actions sont requises !')
                ->success() // Couleur verte
                ->icon('heroicon-o-check-circle')
                ->sendToDatabase($notifiables);
        }
        
        // Tu peux rajouter d'autres règles ici ! 
        // Exemple : if ($folder->wasChanged('status') && $folder->status === 'cancelled') { ... }
    }
}