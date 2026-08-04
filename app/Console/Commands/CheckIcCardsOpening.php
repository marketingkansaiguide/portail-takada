<?php

namespace App\Console\Commands;

use App\Models\FolderItem;
use App\Models\FolderTask;
use App\Models\Setting;
use App\Mail\IcCardOpeningAlertMail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class CheckIcCardsOpening extends Command
{
    protected $signature = 'ic-cards:check-opening';
    protected $description = 'Vérifie l\'ouverture des ventes des Cartes IC et envoie un mail récapitulatif regroupé';

    public function handle()
    {
        $this->info('Vérification de l\'ouverture des ventes des Cartes IC...');

        // Prestations Carte IC (product_id = 3) en attente de validation sur dossiers actifs
        $icItems = FolderItem::with(['folder.agency', 'product', 'itemStatus'])
            ->where('product_id', 3)
            ->whereHas('folder', function ($q) {
                $q->whereNotIn('status', ['draft', 'completed', 'cancelled']);
            })
            ->whereHas('itemStatus', function ($q) {
                $q->where('name', 'En attente de validation');
            })
            ->get();

        $newAlertItems = [];

        foreach ($icItems as $item) {
            $effectiveDate = $item->service_date ?? $item->folder?->start_date;
            if (!$effectiveDate) continue;

            $daysBefore = $item->product?->days_before_opening ?? 0;
            $openingDate = Carbon::parse($effectiveDate)->subDays($daysBefore)->startOfDay();

            // Si aujourd'hui est supérieur ou égal à la date d'ouverture des ventes
            if (now()->startOfDay()->greaterThanOrEqualTo($openingDate)) {
                $taskCode = "ic_cards_opening_{$item->id}";
                
                // Vérifier si la tâche/alerte existe déjà
                $taskExists = FolderTask::where('action_code', $taskCode)->exists();

                if (!$taskExists) {
                    $folderRef = $item->folder?->reference ?? 'N/A';
                    $paxName = $item->folder?->lead_traveler_name ?? 'Client';
                    $qty = $item->quantity ?? 1;

                    $description = "🚨 Cartes IC à commander : Ouverture des ventes atteinte pour le dossier {$folderRef} ({$paxName} - {$qty} carte(s)).";

                    // 1. Création de la tâche sur le Dashboard
                    FolderTask::create([
                        'folder_id' => $item->folder_id,
                        'action_code' => $taskCode,
                        'description' => $description,
                        'icon' => 'heroicon-m-credit-card',
                        'color' => 'text-danger-500',
                        'is_completed' => false,
                    ]);

                    // On collecte la prestation pour le mail récapitulatif unique
                    $newAlertItems[] = $item;
                }
            }
        }

        // 2. Envoi d'un SEUL e-mail récapitulatif s'il y a de nouvelles alertes
        if (count($newAlertItems) > 0) {
            try {
                $setting = Setting::first();
                $adminEmails = [];

                if ($setting && !empty($setting->admin_email_notifications)) {
                    $adminEmails = explode(',', $setting->admin_email_notifications);
                    $adminEmails = array_map('trim', $adminEmails);
                    $adminEmails = array_filter($adminEmails);
                }

                if (empty($adminEmails)) {
                    $adminEmails = [env('MAIL_ADMIN_RECEIVER', env('MAIL_FROM_ADDRESS'))];
                }

                if (!empty($adminEmails)) {
                    Mail::to($adminEmails)->send(new IcCardOpeningAlertMail($newAlertItems));
                    $this->info("E-mail récapitulatif d'alerte Carte IC (" . count($newAlertItems) . " prestation(s)) envoyé à : " . implode(', ', $adminEmails));
                }
            } catch (\Exception $e) {
                Log::error("Erreur envoi email alerte Carte IC : " . $e->getMessage());
                $this->error("Erreur e-mail : " . $e->getMessage());
            }
        } else {
            $this->info("Aucune nouvelle alerte Carte IC à notifier.");
        }

        return Command::SUCCESS;
    }
}