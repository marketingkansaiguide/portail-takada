<?php

namespace App\Filament\Agency\Resources\AgencyUserResource\Pages;

use App\Filament\Agency\Resources\AgencyUserResource;
use App\Models\Agency;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class ListAgencyUsers extends ListRecords
{
    protected static string $resource = AgencyUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 💡 BOUTON POUR GÉRER L'AGENCE DEPUIS CETTE MÊME PAGE
            Actions\Action::make('editAgencyProfile')
                ->label('Profil de l\'Agence')
                ->icon('heroicon-o-building-office-2')
                ->color('info')
                ->modalHeading('Coordonnées de l\'Agence')
                ->modalDescription('Mettez à jour les informations principales de votre agence de voyage. Ces données peuvent être utilisées pour nos échanges ou la facturation.')
                ->modalSubmitActionLabel('Enregistrer les modifications')
                ->fillForm(function () {
                    // On pré-remplit le formulaire avec les infos de l'agence connectée
                    $agencyId = Filament::auth()->user()->agency_id;
                    if (!$agencyId) return [];
                    return Agency::find($agencyId)?->toArray() ?? [];
                })
                ->form([
                    TextInput::make('name')
                        ->label('Nom de l\'agence')
                        ->required()
                        ->maxLength(255),
                    
                    TextInput::make('contact_name')
                        ->label('Nom du contact principal')
                        ->maxLength(255),
                    
                    TextInput::make('email')
                        ->label('Adresse mail de contact principale')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    
                    TextInput::make('phone')
                        ->label('Téléphone')
                        ->tel()
                        ->maxLength(255),
                    
                    Textarea::make('address')
                        ->label('Adresse postale complète')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    // On sauvegarde les modifications dans la base de données
                    $agencyId = Filament::auth()->user()->agency_id;
                    if ($agencyId) {
                        Agency::find($agencyId)->update($data);
                        
                        Notification::make()
                            ->title('Profil de l\'agence mis à jour avec succès !')
                            ->success()
                            ->send();
                    }
                }),

            // Bouton de création d'un vendeur (déjà existant)
            Actions\CreateAction::make()
                ->label('Ajouter un vendeur')
                ->icon('heroicon-o-plus'),
        ];
    }
}