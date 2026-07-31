<?php

namespace App\Filament\Agency\Resources\AgencyFolderResource\Pages;

use App\Filament\Agency\Resources\AgencyFolderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EditAgencyFolder extends EditRecord
{
    protected static string $resource = AgencyFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 1. Bouton : Valider et transmettre le dossier à Takada
            Actions\Action::make('validateFolder')
                ->label('🚀 Valider et transmettre le dossier')
                ->color('success')
                ->size('lg')
                ->icon('heroicon-o-check-circle')
                ->visible(fn ($record) => $record && $record->status === 'draft')
                ->modalHeading('Validation définitive du dossier client')
                ->modalWidth('3xl')
                ->modalSubmitActionLabel('Oui, valider et transmettre le dossier')
                ->modalCancelActionLabel('Annuler')
                ->form(function ($record) {
                    $items = $record->folderItems;
                    $itemsHtml = '';
                    $total = 0;

                    foreach ($items as $item) {
                        $pName = e($item->product->name ?? 'Prestation');
                        $date = $item->service_date ? $item->service_date->format('d/m/Y') : '---';
                        $qty = $item->quantity;
                        $price = number_format($item->total_price, 0, '.', ' ') . ' ¥';
                        $total += $item->total_price;

                        $itemsHtml .= "
                            <tr style='border-bottom: 1px solid #e2e8f0;'>
                                <td style='padding: 8px 10px;'><b>{$pName}</b></td>
                                <td style='padding: 8px 10px; text-align: center;'>{$date}</td>
                                <td style='padding: 8px 10px; text-align: center;'>{$qty} pax</td>
                                <td style='padding: 8px 10px; text-align: right; font-weight: bold;'>{$price}</td>
                            </tr>
                        ";
                    }

                    $totalFormatted = number_format($total, 0, '.', ' ') . ' ¥';

                    return [
                        Placeholder::make('recap_modal')
                            ->hiddenLabel()
                            ->content(new HtmlString("
                                <div style='font-size: 0.9rem;'>
                                    <h4 style='font-weight: bold; margin-bottom: 8px; color: #1e3a8a;'>Récapitulatif des prestations demandées :</h4>
                                    <table style='width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 0.875rem;'>
                                        <thead>
                                            <tr style='background-color: #f1f5f9; text-align: left; color: #475569;'>
                                                <th style='padding: 8px 10px;'>Prestation</th>
                                                <th style='padding: 8px 10px; text-align: center;'>Date</th>
                                                <th style='padding: 8px 10px; text-align: center;'>Participants</th>
                                                <th style='padding: 8px 10px; text-align: right;'>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {$itemsHtml}
                                        </tbody>
                                        <tfoot>
                                            <tr style='background-color: #eff6ff; font-weight: bold; color: #1e3a8a;'>
                                                <td colspan='3' style='padding: 10px;'>TOTAL ESTIMÉ DU DOSSIER :</td>
                                                <td style='padding: 10px; text-align: right; font-size: 1.1rem;'>{$totalFormatted}</td>
                                            </tr>
                                        </tfoot>
                                    </table>

                                    <div style='background-color: #fffbebfb; border: 1px solid #fcd34d; border-radius: 8px; padding: 14px; color: #92400e;'>
                                        <p style='font-weight: bold; margin-top: 0; margin-bottom: 8px; font-size: 0.95rem; color: #b45309;'>
                                            ⚠️ Attention : Engagement de validation
                                        </p>
                                        <ul style='margin: 0; padding-left: 20px; font-size: 0.85rem; line-height: 1.6;'>
                                            <li>Ce dossier va nous être <b>transmis formellement</b> pour traitement et réservation auprès de nos partenaires.</li>
                                            <li>Toute demande ultérieure de modification d'une activité validée <b>ne pourra se faire qu'en nous contactant directement</b> via l'espace de discussion de ce dossier.</li>
                                            <li>Les annulations survenant après la validation du dossier seront rigoureusement <b>soumises à nos conditions d'annulation</b>.</li>
                                            <li>Vous conserverez la possibilité d'ajouter de nouvelles prestations à ce dossier ultérieurement.</li>
                                        </ul>
                                    </div>
                                </div>
                            "))
                    ];
                })
                ->action(function ($record) {
                    $record->update(['status' => 'confirmed']);

                    try {
                        if (class_exists('\App\Models\FolderTask')) {
                            \App\Models\FolderTask::create([
                                'folder_id' => $record->id,
                                'title' => "Nouveau dossier validé par l'agence : {$record->folder_name} ({$record->reference})",
                                'status' => 'pending',
                                'action_code' => "new_folder_confirmed_{$record->id}",
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error("Erreur création tâche admin : " . $e->getMessage());
                    }

                    try {
                        $adminEmail = config('mail.admin_alert_address') 
                            ?? env('ADMIN_ALERT_EMAIL') 
                            ?? \App\Models\User::where('is_admin', true)->value('email');

                        if ($adminEmail) {
                            Mail::raw(
                                "Un nouveau dossier vient d'être validé par l'agence " . ($record->agency->name ?? 'Inconnue') . ".\n\n" .
                                "Référence : {$record->reference}\n" .
                                "Nom du dossier : {$record->folder_name}\n" .
                                "Pax Leader : {$record->lead_traveler_name}\n" .
                                "Nombre de prestations : " . $record->folderItems()->count() . "\n" .
                                "Montant Total : " . number_format($record->total_price, 0, '.', ' ') . " ¥\n\n" .
                                "Accéder au dossier dans l'Admin : " . route('filament.admin.resources.folders.edit', ['record' => $record->id]),
                                function ($message) use ($adminEmail, $record) {
                                    $message->to($adminEmail)
                                            ->subject("🚨 [NOUVEAU DOSSIER VALIDÉ] {$record->reference} - {$record->folder_name}");
                                }
                            );
                        }
                    } catch (\Exception $e) {
                        Log::error("Erreur envoi mail alerte admin : " . $e->getMessage());
                    }

                    try {
                        if (class_exists('\App\Models\FolderHistory')) {
                            \App\Models\FolderHistory::logConsolidated(
                                $record->id,
                                'Validation du dossier',
                                "Le dossier a été formellement validé par l'agence et transmis à l'équipe Takada."
                            );
                        }
                    } catch (\Exception $e) {}

                    \Filament\Notifications\Notification::make()
                        ->title('Dossier validé et transmis avec succès !')
                        ->body('Votre dossier est désormais pris en charge par l\'équipe Takada.')
                        ->success()
                        ->send();
                }),

            // 2. Bouton : Annuler / Supprimer le brouillon
            Actions\Action::make('cancelFolderDraft')
                ->label('🗑️ Annuler / Supprimer le brouillon')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Annuler et supprimer ce dossier brouillon ?')
                ->modalDescription('Ce dossier n\'a pas encore été transmitted. Sa suppression entraînera le retrait définitif du brouillon.')
                ->modalSubmitActionLabel('Oui, supprimer le brouillon')
                ->modalCancelActionLabel('Conserver le brouillon')
                ->visible(fn ($record) => $record && $record->status === 'draft')
                ->action(function ($record) {
                    $record->delete();

                    \Filament\Notifications\Notification::make()
                        ->title('Brouillon supprimé')
                        ->body('Le dossier brouillon a été annulé avec succès.')
                        ->success()
                        ->send();

                    return redirect(AgencyFolderResource::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $totalItems = 0;
        if (isset($data['folderItems']) && is_array($data['folderItems'])) {
            foreach ($data['folderItems'] as $item) {
                $totalItems += (float) ($item['total_price'] ?? 0);
            }
        }
        $data['total_price'] = $totalItems;
        
        return $data;
    }
}