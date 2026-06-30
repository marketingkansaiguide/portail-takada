<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Barryvdh\DomPDF\Facade\Pdf;

class EditFolder extends EditRecord
{
    protected static string $resource = FolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 🎯 BOUTON D'EXPORTATION AUTOMATIQUE DU RÉCAPITULATIF PDF
            Action::make('exportRecap')
                ->label(__('Exporter le récapitulatif'))
                ->color('info')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    $record = $this->getRecord();
                    
                    // Chargement des relations pour éviter les requêtes SQL en boucle
                    $record->load(['agency', 'folderItems.product', 'folderPassengers']);

                    // Calcul des totaux
                    $itemsTotal = $record->folderItems->sum('total_price');
                    $grandTotal = $itemsTotal + $record->folder_fee;
                    $totalPax = $record->pax_adults + $record->pax_children;

                    // Génération du PDF à partir d'une vue Blade propre
                    $pdf = Pdf::loadView('pdf.recapitulatif', [
                        'folder' => $record,
                        'agency' => $record->agency,
                        'items' => $record->folderItems,
                        'itemsTotal' => $itemsTotal,
                        'grandTotal' => $grandTotal,
                        'totalPax' => $totalPax,
                        'dateEmit' => now()->format('d/m/Y'),
                    ]);

                    // Lance le téléchargement instantané du fichier sur le navigateur de l'admin
                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        "recapitulatif_{$record->reference}.pdf"
                    );
                }),

            DeleteAction::make(),
        ];
    }
}