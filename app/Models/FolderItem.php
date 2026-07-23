<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FolderItem extends Model
{
    protected $fillable = [
        'folder_id', 'product_id', 'product_option_id', 'supplier_id', 'item_status_id',
        'service_date', 'quantity', 'purchase_unit_price', 'purchase_total_price', 'unit_price', 'total_price', 'custom_values', 
        'selected_options', 'invoice_received_at', 'google_calendar_event_id',
    ];

    protected $casts = [
        'service_date' => 'date', 'quantity' => 'integer',
        'purchase_unit_price' => 'integer', 'purchase_total_price' => 'integer',
        'unit_price' => 'integer', 'total_price' => 'integer', 
        'custom_values' => 'array', 'selected_options' => 'array',
        'invoice_received_at' => 'date',
    ];

    public function getProductSupplierData()
    {
        if ($this->supplier_id) {
            return \App\Models\ProductSupplier::where('product_id', $this->product_id)
                ->where('supplier_id', $this->supplier_id)
                ->first();
        }
        return \App\Models\ProductSupplier::where('product_id', $this->product_id)->first();
    }

    public function getTargetSupplier()
    {
        if ($this->supplier_id) {
            return \App\Models\Supplier::find($this->supplier_id);
        }
        $ps = $this->getProductSupplierData();
        return $ps ? \App\Models\Supplier::find($ps->supplier_id) : null;
    }

    public function parseSupplierEmailSubject(): string
    {
        $productSupplier = $this->getProductSupplierData();
        
        $template = "ご予約依頼 : [DOSSIER_REF] / [LEAD_NAME]"; // Défaut
        if ($productSupplier && !empty($productSupplier->email_subject)) {
            $template = $productSupplier->email_subject;
        }

        $folder = $this->folder;
        $dossierRef = $folder ? $folder->reference : 'N/A';
        $leadName = $folder ? $folder->lead_traveler_name : 'N/A';
        $datePresta = $this->service_date ? $this->service_date->format('d/m/Y') : 'Non définie';
        $datePrestaJp = $this->service_date ? $this->service_date->format('Y年m月d日') : 'Non définie';
        $quantite = $this->quantity ?? 1;

        $writerName = auth()->check() ? auth()->user()->name : 'L\'équipe Takada';
        $targetSupplier = $this->getTargetSupplier();
        $supplierContact = ($targetSupplier && $targetSupplier->contact_name) ? $targetSupplier->contact_name : 'Partenaire';

        $replacements = [
            '[DOSSIER_REF]' => $dossierRef,
            '[LEAD_NAME]' => $leadName,
            '[DATE_PRESTA]' => $datePresta,
            '[DATE_PRESTA_JP]' => $datePrestaJp,
            '[QUANTITE]' => $quantite,
            '[NOM_AGENT]' => $writerName,
            '[CONTACT_FOURNISSEUR]' => $supplierContact,
        ];

        return trim(str_replace(array_keys($replacements), array_values($replacements), $template));
    }

    public function parseSupplierFaxHeader(): string
    {
        $productSupplier = $this->getProductSupplierData();
        $templateData = "ご担当者様"; // Défaut
        
        if ($productSupplier && !empty($productSupplier->fax_header)) {
            $templateData = is_array($productSupplier->fax_header) ? ($productSupplier->fax_header['to_contact_name'] ?? 'ご担当者様') : $productSupplier->fax_header;
        }

        $folder = $this->folder;
        $dossierRef = $folder ? $folder->reference : 'N/A';
        $leadName = $folder ? $folder->lead_traveler_name : 'N/A';
        $datePresta = $this->service_date ? $this->service_date->format('d/m/Y') : 'Non définie';
        $datePrestaJp = $this->service_date ? $this->service_date->format('Y年m月d日') : 'Non définie';
        $quantite = $this->quantity ?? 1;

        $writerName = auth()->check() ? auth()->user()->name : 'L\'équipe Takada';
        $targetSupplier = $this->getTargetSupplier();
        $supplierContact = ($targetSupplier && $targetSupplier->contact_name) ? $targetSupplier->contact_name : 'Partenaire';

        $replacements = [
            '[DOSSIER_REF]' => $dossierRef,
            '[LEAD_NAME]' => $leadName,
            '[DATE_PRESTA]' => $datePresta,
            '[DATE_PRESTA_JP]' => $datePrestaJp,
            '[QUANTITE]' => $quantite,
            '[NOM_AGENT]' => $writerName,
            '[CONTACT_FOURNISSEUR]' => $supplierContact,
        ];

        $templateString = is_array($templateData) ? json_encode($templateData) : (string)$templateData;
        return trim(str_replace(array_keys($replacements), array_values($replacements), $templateString));
    }

    public function parseSupplierEmail(): string
    {
        $product = $this->product;
        $productSupplier = $this->getProductSupplierData();

        $emailRendered = "Aucun modèle d'e-mail n'a été configuré pour ce fournisseur.";

        if ($productSupplier && !empty($productSupplier->email_template)) {
            $emailRendered = $productSupplier->email_template;
        } else {
            return $emailRendered;
        }

        $folder = $this->folder;

        $dossierRef = $folder ? $folder->reference : 'N/A';
        $leadName = $folder ? $folder->lead_traveler_name : 'N/A';
        $datePresta = $this->service_date ? $this->service_date->format('d/m/Y') : 'Non définie';
        $datePrestaJp = $this->service_date ? $this->service_date->format('Y年m月d日') : 'Non définie';
        $quantite = $this->quantity ?? 1;
        
        $writerName = auth()->check() ? auth()->user()->name : 'L\'équipe Takada';
        $targetSupplier = $this->getTargetSupplier();
        $supplierContact = ($targetSupplier && $targetSupplier->contact_name) ? $targetSupplier->contact_name : 'Partenaire';

        $selectedOpts = is_string($this->selected_options) ? json_decode($this->selected_options, true) : $this->selected_options;

        $paxAdults = 0;
        $paxChildren = 0;
        $childLimit = $product->child_age_limit ?? 11;
        $ageCalcDate = $this->service_date ? Carbon::parse($this->service_date) : Carbon::now();

        $passagersText = "";
        if ($folder && $folder->folderPassengers && $folder->folderPassengers->isNotEmpty()) {
            foreach ($folder->folderPassengers as $index => $passenger) {
                $num = $index + 1;
                $birthStr = $passenger->birth_date ? Carbon::parse($passenger->birth_date)->format('d/m/Y') : 'Inconnue';
                
                if ($passenger->birth_date) {
                    $ageNum = Carbon::parse($passenger->birth_date)->diffInYears($ageCalcDate);
                    $ageStr = $ageNum . ' ans';
                    
                    if ($ageNum <= $childLimit) {
                        $paxChildren++;
                    } else {
                        $paxAdults++;
                    }
                } else {
                    $ageStr = 'Âge inconnu';
                    $paxAdults++;
                }

                $passagersText .= "{$num}. {$passenger->last_name} {$passenger->first_name} ({$passenger->nationality}) - Né(e) le {$birthStr} [{$ageStr}]\n";
            }
        } else {
            $passagersText = "Aucun passager enregistré.";
            $paxAdults = $quantite;
        }

        if (preg_match_all('/\[IF_OPTION:([^\]]+)\](.*?)\[\/IF_OPTION\]/is', $emailRendered, $matches)) {
            foreach ($matches[1] as $index => $optionCode) {
                $optionCode = trim($optionCode);
                $fullBlock = $matches[0][$index];
                $innerText = $matches[2][$index];
                $keepBlock = false;

                $optionModel = \App\Models\ProductOption::where('product_id', $product->id)->where('code', $optionCode)->first();

                if ($optionModel) {
                    if (!empty($selectedOpts) && is_array($selectedOpts)) {
                        foreach ($selectedOpts as $optData) {
                            if (!empty($optData['product_option_id']) && $optData['product_option_id'] == $optionModel->id) {
                                $keepBlock = true;
                                break;
                            }
                        }
                    } elseif (!empty($this->product_option_id) && $this->product_option_id == $optionModel->id) {
                        $keepBlock = true; 
                    }
                }
                
                if ($keepBlock) {
                    $emailRendered = str_replace($fullBlock, $innerText, $emailRendered);
                } else {
                    $escapedBlock = preg_quote($fullBlock, '/');
                    $emailRendered = preg_replace('/^[ \t]*' . $escapedBlock . '[ \t]*(\r?\n)?/m', '', $emailRendered);
                    $emailRendered = str_replace($fullBlock, '', $emailRendered);
                }
            }
        }

        if (preg_match_all('/\[IF_QUANTITY(>=|<=|>|<|==)(\d+)\](.*?)\[\/IF_QUANTITY\]/is', $emailRendered, $matches)) {
            foreach ($matches[1] as $index => $operator) {
                $fullBlock = $matches[0][$index];
                $compareValue = (int) $matches[2][$index];
                $innerText = $matches[3][$index];
                $currentQty = (int) $quantite;
                $keepBlock = false;

                switch ($operator) {
                    case '>=': $keepBlock = ($currentQty >= $compareValue); break;
                    case '<=': $keepBlock = ($currentQty <= $compareValue); break;
                    case '>':  $keepBlock = ($currentQty > $compareValue); break;
                    case '<':  $keepBlock = ($currentQty < $compareValue); break;
                    case '==': $keepBlock = ($currentQty == $compareValue); break;
                }

                if ($keepBlock) {
                    $emailRendered = str_replace($fullBlock, $innerText, $emailRendered);
                } else {
                    $escapedBlock = preg_quote($fullBlock, '/');
                    $emailRendered = preg_replace('/^[ \t]*' . $escapedBlock . '[ \t]*(\r?\n)?/m', '', $emailRendered);
                    $emailRendered = str_replace($fullBlock, '', $emailRendered);
                }
            }
        }

        if (preg_match_all('/\[IF_PAX_CHILDREN(>=|<=|>|<|==)(\d+)\](.*?)\[\/IF_PAX_CHILDREN\]/is', $emailRendered, $matches)) {
            foreach ($matches[1] as $index => $operator) {
                $fullBlock = $matches[0][$index];
                $compareValue = (int) $matches[2][$index];
                $innerText = $matches[3][$index];
                $keepBlock = false;

                switch ($operator) {
                    case '>=': $keepBlock = ($paxChildren >= $compareValue); break;
                    case '<=': $keepBlock = ($paxChildren <= $compareValue); break;
                    case '>':  $keepBlock = ($paxChildren > $compareValue); break;
                    case '<':  $keepBlock = ($paxChildren < $compareValue); break;
                    case '==': $keepBlock = ($paxChildren == $compareValue); break;
                }

                if ($keepBlock) {
                    $emailRendered = str_replace($fullBlock, $innerText, $emailRendered);
                } else {
                    $escapedBlock = preg_quote($fullBlock, '/');
                    $emailRendered = preg_replace('/^[ \t]*' . $escapedBlock . '[ \t]*(\r?\n)?/m', '', $emailRendered);
                    $emailRendered = str_replace($fullBlock, '', $emailRendered);
                }
            }
        }

        if (preg_match_all('/\[IF_PAX_ADULTS(>=|<=|>|<|==)(\d+)\](.*?)\[\/IF_PAX_ADULTS\]/is', $emailRendered, $matches)) {
            foreach ($matches[1] as $index => $operator) {
                $fullBlock = $matches[0][$index];
                $compareValue = (int) $matches[2][$index];
                $innerText = $matches[3][$index];
                $keepBlock = false;

                switch ($operator) {
                    case '>=': $keepBlock = ($paxAdults >= $compareValue); break;
                    case '<=': $keepBlock = ($paxAdults <= $compareValue); break;
                    case '>':  $keepBlock = ($paxAdults > $compareValue); break;
                    case '<':  $keepBlock = ($paxAdults < $compareValue); break;
                    case '==': $keepBlock = ($paxAdults == $compareValue); break;
                }

                if ($keepBlock) {
                    $emailRendered = str_replace($fullBlock, $innerText, $emailRendered);
                } else {
                    $escapedBlock = preg_quote($fullBlock, '/');
                    $emailRendered = preg_replace('/^[ \t]*' . $escapedBlock . '[ \t]*(\r?\n)?/m', '', $emailRendered);
                    $emailRendered = str_replace($fullBlock, '', $emailRendered);
                }
            }
        }

        $optionNames = [];
        if (!empty($selectedOpts) && is_array($selectedOpts)) {
            foreach ($selectedOpts as $optData) {
                if (!empty($optData['product_option_id'])) {
                    $opt = \App\Models\ProductOption::find($optData['product_option_id']);
                    if ($opt) {
                        $str = $opt->name;
                        if ($opt->billing_type === 'manual') {
                            $qty = $optData['quantity'] ?? 1;
                            $str .= " (Qté: {$qty})";
                        }
                        $optionNames[] = $str;
                    }
                }
            }
        } elseif (!empty($this->product_option_id)) {
            $opt = $this->productOption;
            if ($opt) $optionNames[] = $opt->name;
        }
        $optionName = implode(', ', $optionNames);

        $replacements = [
            '[DOSSIER_REF]' => $dossierRef,
            '[LEAD_NAME]' => $leadName,
            '[DATE_PRESTA]' => $datePresta,
            '[DATE_PRESTA_JP]' => $datePrestaJp,
            '[QUANTITE]' => $quantite,
            '[PAX_ADULTS]' => $paxAdults,
            '[PAX_CHILDREN]' => $paxChildren,
            '[OPTION_NAME]' => $optionName,
            '[LISTE_PASSAGERS]' => trim($passagersText),
            '[NOM_AGENT]' => $writerName,
            '[CONTACT_FOURNISSEUR]' => $supplierContact,
        ];

        $emailRendered = str_replace(array_keys($replacements), array_values($replacements), $emailRendered);

        if (is_array($this->custom_values)) {
            foreach ($this->custom_values as $key => $val) {
                if (is_bool($val)) {
                    $userValue = $val ? __('Oui') : __('Non');
                } elseif (is_array($val)) {
                    $userValue = implode(', ', $val);
                } elseif (empty($val) && $val !== 0 && $val !== '0') {
                    $userValue = '';
                } else {
                    $userValue = (string) $val;
                }
                
                $emailRendered = str_replace("[CUSTOM:{$key}]", $userValue, $emailRendered);
            }
        }

        if (preg_match_all('/\[OPTION:([^\]]+)\]/', $emailRendered, $matches)) {
            foreach ($matches[1] as $index => $optionCode) {
                $optionCode = trim($optionCode);
                $shortcode = $matches[0][$index];
                $optionValue = '0';

                $optionModel = \App\Models\ProductOption::where('product_id', $product->id)->where('code', $optionCode)->first();

                if ($optionModel) {
                    $isSelected = false;
                    $optQty = 0;

                    if (!empty($selectedOpts) && is_array($selectedOpts)) {
                        foreach ($selectedOpts as $optData) {
                            if (!empty($optData['product_option_id']) && $optData['product_option_id'] == $optionModel->id) {
                                $isSelected = true;
                                if ($optionModel->billing_type === 'manual') {
                                    $optQty = (int)($optData['quantity'] ?? 1);
                                } elseif ($optionModel->billing_type === 'per_pax') {
                                    $optQty = $this->quantity ?? 1;
                                } else {
                                    $optQty = 1;
                                }
                                break;
                            }
                        }
                    } elseif (!empty($this->product_option_id) && $this->product_option_id == $optionModel->id) {
                        $isSelected = true;
                        $optQty = ($optionModel->billing_type === 'per_pax') ? ($this->quantity ?? 1) : 1;
                    }

                    if ($isSelected) {
                        $optionValue = (string) $optQty;
                    }
                }

                $emailRendered = str_replace($shortcode, $optionValue, $emailRendered);
            }
        }

        $emailRendered = preg_replace("/(\r?\n){3,}/", "\n\n", $emailRendered);

        return trim($emailRendered);
    }

    // 💡 Synchronisation avec l'agenda Google (AVEC TRACEURS LOG)
    public function syncGoogleCalendar()
    {
        Log::info("=== CALENDAR : Début syncGoogleCalendar pour FolderItem ID: {$this->id} ===");

        $calendarId = env('GOOGLE_CALENDAR_ID');
        if (!$calendarId) {
            Log::warning("CALENDAR : Arrêt -> GOOGLE_CALENDAR_ID manquant dans le fichier .env");
            return;
        }

        $keyFilePath = storage_path('app/google-credentials.json');
        if (!file_exists($keyFilePath)) {
            Log::warning("CALENDAR : Arrêt -> Le fichier credentials ({$keyFilePath}) est introuvable.");
            return;
        }

        $folder = $this->folder;
        $product = $this->product;
        
        $hasDate = $this->service_date ? 'OUI' : 'NON';
        $hasProduct = $product ? 'OUI' : 'NON';
        $delay = $product->booking_open_delay ?? 'NULL';
        $status = $folder->status ?? 'INCONNU';

        Log::info("CALENDAR : Check des données -> Date:{$hasDate} | Produit:{$hasProduct} | Délai d'achat:{$delay} | Statut dossier:{$status}");

        if (!$this->service_date || !$product || $product->booking_open_delay === null || ($folder && in_array($folder->status, ['cancelled', 'completed']))) {
            Log::info("CALENDAR : Arrêt -> Condition non remplie. (Pas de date, pas de délai, ou dossier annulé/terminé). Appel de la fonction Delete.");
            $this->deleteGoogleCalendarEvent();
            return;
        }

        Log::info("CALENDAR : Toutes les conditions métiers sont valides. Préparation de l'événement API...");

        // Calcul de la date d'ouverture théorique des achats
        $openDate = Carbon::parse($this->service_date)->subDays($product->booking_open_delay)->startOfDay();
        
        // Si la date théorique est déjà passée, on force l'événement à AUJOURD'HUI
        if ($openDate->lessThan(Carbon::today())) {
            Log::info("CALENDAR : La date théorique d'ouverture ({$openDate->format('d/m/Y')}) est dépassée, événement forcé à AUJOURD'HUI.");
            $openDate = Carbon::today();
        }
        
        $folderName = $folder ? $folder->folder_name : 'N/A';
        $productName = $product->name;
        $supplierName = $this->getTargetSupplier()?->name ?? 'Aucun fournisseur';
        $qty = $this->quantity ?? 1;

        $client = new \Google\Client();
        $client->setAuthConfig($keyFilePath);
        $client->addScope(\Google\Service\Calendar::CALENDAR);
        $service = new \Google\Service\Calendar($client);

        $event = new \Google\Service\Calendar\Event([
            'summary' => "🛒 ACHAT : {$productName} ({$folderName})",
            'description' => "Dossier : {$folderName}\nPrestation : {$productName}\nFournisseur : {$supplierName}\nQuantité/Pax : {$qty}\nDate de la prestation : {$this->service_date->format('d/m/Y')}\n\nOuvrir le dossier : " . route('filament.admin.resources.folders.edit', ['record' => $this->folder_id]),
            'start' => ['date' => $openDate->format('Y-m-d')],
            'end' => ['date' => $openDate->copy()->addDay()->format('Y-m-d')],
        ]);

        try {
            if ($this->google_calendar_event_id) {
                Log::info("CALENDAR : Événement déjà existant (ID: {$this->google_calendar_event_id}), tentative de mise à jour...");
                try {
                    $service->events->update($calendarId, $this->google_calendar_event_id, $event);
                    Log::info("CALENDAR : Mise à jour réussie !");
                } catch (\Exception $e) {
                    Log::info("CALENDAR : La mise à jour a échoué (probablement supprimé manuellement). Tentative de recréation...");
                    $created = $service->events->insert($calendarId, $event);
                    DB::table('folder_items')->where('id', $this->id)->update(['google_calendar_event_id' => $created->id]);
                    Log::info("CALENDAR : Recréation réussie (ID: {$created->id}) !");
                }
            } else {
                Log::info("CALENDAR : Nouvel événement, tentative de création...");
                $created = $service->events->insert($calendarId, $event);
                DB::table('folder_items')->where('id', $this->id)->update(['google_calendar_event_id' => $created->id]);
                Log::info("CALENDAR : Création réussie (ID: {$created->id}) !");
            }
        } catch (\Exception $e) {
            Log::error("CALENDAR : Erreur CRITIQUE de l'API Google -> " . $e->getMessage());
            throw $e; 
        }
    }

    public function deleteGoogleCalendarEvent()
    {
        if (!$this->google_calendar_event_id) return;
        
        $calendarId = env('GOOGLE_CALENDAR_ID');
        $keyFilePath = storage_path('app/google-credentials.json');
        
        if ($calendarId && file_exists($keyFilePath)) {
            try {
                $client = new \Google\Client();
                $client->setAuthConfig($keyFilePath);
                $client->addScope(\Google\Service\Calendar::CALENDAR);
                $service = new \Google\Service\Calendar($client);

                $service->events->delete($calendarId, $this->google_calendar_event_id);
                Log::info("CALENDAR : Événement supprimé avec succès de Google Agenda.");
            } catch (\Exception $e) {
                Log::warning("CALENDAR : Impossible de supprimer l'événement (peut-être déjà effacé manuellement).");
            }
        }

        DB::table('folder_items')->where('id', $this->id)->update(['google_calendar_event_id' => null]);
    }

    protected static function booted()
    {
        static::creating(function ($item) {
            if (empty($item->item_status_id)) {
                $defaultStatus = \App\Models\ItemStatus::firstOrCreate(
                    ['name' => 'En attente de validation'],
                    ['color' => 'warning']
                );
                
                $item->item_status_id = $defaultStatus->id;
            }
        });

        static::updated(function ($item) {
            static $processedUpdates = [];

            if ($item->wasChanged()) {
                $changes = $item->getChanges();
                unset($changes['updated_at']);

                if (!empty($changes)) {
                    $fingerprint = $item->id . '_' . md5(json_encode($changes));
                    
                    if (isset($processedUpdates[$fingerprint])) return;
                    $processedUpdates[$fingerprint] = true;

                    // 💡 AJOUT : Mise à jour de l'agenda Google avec Logs
                    if (array_intersect_key($changes, array_flip(['service_date', 'product_id', 'supplier_id', 'quantity']))) {
                        Log::info("CALENDAR : Hook 'updated' déclenché pour FolderItem ID: {$item->id}");
                        try { 
                            $item->syncGoogleCalendar(); 
                        } catch (\Exception $e) {
                            Log::error("CALENDAR : Échec global lors du Hook (Update) -> " . $e->getMessage());
                        }
                    }

                    $productName = $item->product ? $item->product->name : 'Une prestation';
                    if ($item->service_date) {
                        $productName .= ' (du ' . Carbon::parse($item->service_date)->format('d/m/Y') . ')';
                    } elseif ($item->id) {
                        $productName .= ' (Réf: #' . $item->id . ')';
                    }

                    $changesText = [];
                    
                    $labels = [
                        'item_status_id' => 'Statut de la prestation',
                        'supplier_id' => 'Fournisseur',
                        'service_date' => 'Date de service',
                        'quantity' => 'Quantité',
                        'purchase_unit_price' => 'Prix d\'achat unitaire',
                        'purchase_total_price' => 'Prix d\'achat total',
                        'unit_price' => 'Prix de vente unitaire',
                        'total_price' => 'Prix de vente total',
                        'selected_options' => 'Options sélectionnées',
                        'invoice_received_at' => 'Date de réception facture',
                    ];

                    foreach ($changes as $key => $newValue) {
                        $oldValue = $item->getOriginal($key);

                        if ($key === 'custom_values') {
                            $oldCustom = is_string($oldValue) ? json_decode($oldValue, true) : (is_array($oldValue) ? $oldValue : []);
                            $newCustom = is_string($newValue) ? json_decode($newValue, true) : (is_array($newValue) ? $newValue : []);

                            $oldCustom = $oldCustom ?: [];
                            $newCustom = $newCustom ?: [];

                            $allKeys = array_unique(array_merge(array_keys($oldCustom), array_keys($newCustom)));

                            foreach ($allKeys as $k) {
                                $oVal = isset($oldCustom[$k]) ? $oldCustom[$k] : 'Vide';
                                $nVal = isset($newCustom[$k]) ? $newCustom[$k] : 'Vide';

                                if (is_array($oVal)) $oVal = implode(', ', $oVal);
                                if (is_array($nVal)) $nVal = implode(', ', $nVal);

                                $oldV = is_bool($oVal) ? ($oVal ? 'Oui' : 'Non') : (string)$oVal;
                                $newV = is_bool($nVal) ? ($nVal ? 'Oui' : 'Non') : (string)$nVal;

                                if ($oldV === '') $oldV = 'Vide';
                                if ($newV === '') $newV = 'Vide';

                                if ($oldV !== $newV) {
                                    $changesText[] = "• Information '{$k}' : '{$oldV}' ➔ '{$newV}'";
                                }
                            }
                            continue;
                        }

                        if (!array_key_exists($key, $labels)) continue;

                        if ($key === 'item_status_id') {
                            $oldStatus = $oldValue ? (\App\Models\ItemStatus::find($oldValue)?->name ?? 'Inconnu') : 'Aucun';
                            $newStatus = $newValue ? (\App\Models\ItemStatus::find($newValue)?->name ?? 'Inconnu') : 'Aucun';
                            $changesText[] = "• {$labels[$key]} : '{$oldStatus}' ➔ '{$newStatus}'";
                            continue;
                        }
                        
                        if ($key === 'supplier_id') {
                            $oldSupp = $oldValue ? (\App\Models\Supplier::find($oldValue)?->name ?? 'Par défaut') : 'Par défaut';
                            $newSupp = $newValue ? (\App\Models\Supplier::find($newValue)?->name ?? 'Par défaut') : 'Par défaut';
                            $changesText[] = "• {$labels[$key]} : '{$oldSupp}' ➔ '{$newSupp}'";
                            continue;
                        }

                        if ($key === 'service_date' || $key === 'invoice_received_at') {
                            $oldDate = $oldValue ? Carbon::parse($oldValue)->format('d/m/Y') : 'Non renseignée';
                            $newDate = $newValue ? Carbon::parse($newValue)->format('d/m/Y') : 'Vide';
                            $changesText[] = "• {$labels[$key]} : '{$oldDate}' ➔ '{$newDate}'";
                            continue;
                        }

                        if ($key === 'selected_options') {
                            $changesText[] = "• Les 'Options sélectionnées' ont été modifiées.";
                            continue;
                        }

                        $oldString = is_array($oldValue) ? json_encode($oldValue) : ($oldValue !== null && $oldValue !== '' ? (string)$oldValue : 'Non renseigné');
                        $newString = is_array($newValue) ? json_encode($newValue) : ($newValue !== null && $newValue !== '' ? (string)$newValue : 'Vide');
                        $changesText[] = "• {$labels[$key]} : '{$oldString}' ➔ '{$newString}'";
                    }

                    if (!empty($changesText)) {
                        $summary = "La prestation '{$productName}' a été modifiée :\n" . implode("\n", $changesText);
                        \App\Models\FolderHistory::logConsolidated($item->folder_id, 'Mise à jour Prestation', $summary);
                    }
                }
            }
        });

        static::created(function ($item) {
            static $processedCreations = [];
            if (isset($processedCreations[$item->id])) return;
            $processedCreations[$item->id] = true;

            // 💡 AJOUT : Création sur l'agenda Google avec Logs
            Log::info("CALENDAR : Hook 'created' déclenché pour FolderItem ID: {$item->id}");
            try { 
                $item->syncGoogleCalendar(); 
            } catch (\Exception $e) {
                Log::error("CALENDAR : Échec global lors du Hook (Create) -> " . $e->getMessage());
            }

            $productName = $item->product ? $item->product->name : 'Une prestation';
            if ($item->service_date) {
                $productName .= ' (du ' . Carbon::parse($item->service_date)->format('d/m/Y') . ')';
            }

            \App\Models\FolderHistory::logConsolidated($item->folder_id, 'Ajout Prestation', "La prestation '{$productName}' a été ajoutée au dossier.");
        });

        static::deleted(function ($item) {
            // 💡 AJOUT : Suppression de l'agenda Google
            try { 
                $item->deleteGoogleCalendarEvent(); 
            } catch (\Exception $e) {}

            $productName = $item->product ? $item->product->name : 'Une prestation';
            if ($item->service_date) {
                $productName .= ' (du ' . Carbon::parse($item->service_date)->format('d/m/Y') . ')';
            }

            \App\Models\FolderHistory::logConsolidated($item->folder_id, 'Suppression Prestation', "La prestation '{$productName}' a été retirée du dossier.");
        });
    }

    public function folder(): BelongsTo { return $this->belongsTo(Folder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function productOption(): BelongsTo { return $this->belongsTo(ProductOption::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class, 'supplier_id'); }
    public function itemStatus(): BelongsTo { return $this->belongsTo(ItemStatus::class); }
}