<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FolderItem extends Model
{
    protected $fillable = [
        'folder_id', 'product_id', 'product_option_id', 'supplier_id', 'item_status_id',
        'service_date', 'quantity', 'pax_adults', 'pax_children', 'pax_teenagers', 'purchase_unit_price', 'purchase_total_price', 'unit_price', 'total_price', 'custom_values',
        'selected_options', 'invoice_received_at', 'google_calendar_event_id',
        'guide_mission_id', 'guide_sync_status', 'guide_sync_error', 'guide_synced_at',
        'title', 'is_internal', // 💡 AJOUT OBLIGATOIRE POUR LES PRESTATIONS INTERNES
        'label_exported_at',
    ];

    protected $casts = [
        'service_date' => 'date', 'quantity' => 'integer', 'pax_adults' => 'integer', 'pax_children' => 'integer', 'pax_teenagers' => 'integer',
        'purchase_unit_price' => 'integer', 'purchase_total_price' => 'integer',
        'unit_price' => 'integer', 'total_price' => 'integer', 
        'custom_values' => 'array', 'selected_options' => 'array',
        'invoice_received_at' => 'date',
        'is_internal' => 'boolean',
        'label_exported_at' => 'datetime',
        'guide_mission_id' => 'integer',
        'guide_synced_at' => 'datetime',
    ];

    /**
     * Prestations facturables : les annulations et indisponibilités sont exclues.
     */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->whereDoesntHave('itemStatus', fn (Builder $statusQuery) => $statusQuery
            ->whereRaw('(LOWER(name) LIKE ? OR LOWER(name) LIKE ?)', ['%annul%', '%pas de disponibilit%']));
    }

    public function isCancelled(): bool
    {
        return str_contains(Str::lower(Str::ascii((string) $this->itemStatus?->name)), 'annul');
    }

    /** @param array<int, array<string, mixed>> $items */
    public static function sumBillableFormItems(array $items): float
    {
        $nonBillableStatusIds = ItemStatus::query()
            ->whereRaw('(LOWER(name) LIKE ? OR LOWER(name) LIKE ?)', ['%annul%', '%pas de disponibilit%'])
            ->pluck('id')
            ->all();

        return collect($items)
            ->reject(fn (array $item): bool => in_array((int) ($item['item_status_id'] ?? 0), $nonBillableStatusIds, true))
            ->sum(fn (array $item): float => (float) ($item['total_price'] ?? 0));
    }

    /**
     * Retourne la composition des passagers pour cette prestation.
     *
     * Les prestations avec sélecteur d'âge enregistrent leurs propres
     * compteurs dans custom_values._age_selector. Pour une ancienne
     * prestation dont ces compteurs ne sont pas encore enregistrés, le
     * produit permet tout de même d'identifier le mode adulte/enfant et
     * quantity sert de fallback pour le nombre total.
     */
    public function getPaxBreakdown(): array
    {
        $directAdults = $this->pax_adults;
        $directChildren = $this->pax_children;
        $directTeenagers = $this->pax_teenagers;

        if ($directAdults !== null || $directChildren !== null || $directTeenagers !== null) {
            $adults = max(0, (int) ($directAdults ?? 0));
            $children = max(0, (int) ($directChildren ?? 0));
            $teenagers = max(0, (int) ($directTeenagers ?? 0));

            if (($adults + $children + $teenagers) > 0) {
                return [
                    'adults' => $adults,
                    'children' => $children,
                    'teenagers' => $teenagers,
                    'total' => $adults + $children + $teenagers,
                    'has_age_breakdown' => true,
                ];
            }
        }

        $customValues = is_array($this->custom_values) ? $this->custom_values : [];
        $ageSelector = $customValues['_age_selector'] ?? $customValues['age_selector'] ?? null;
        $ageSelector = is_array($ageSelector) ? $ageSelector : [];
        $productUsesAgeSelector = (bool) $this->product?->enable_age_selector;

        if ($productUsesAgeSelector || $ageSelector !== []) {
        $adults = max(0, (int) ($ageSelector['adults'] ?? 0));
        $children = max(0, (int) ($ageSelector['children'] ?? 0));
        $teenagers = max(0, (int) ($ageSelector['teenagers'] ?? 0));

            if (($adults + $children + $teenagers) === 0) {
                $adults = max(1, (int) ($this->quantity ?? 1));
            }

            return [
                'adults' => $adults,
                'children' => $children,
                'teenagers' => $teenagers,
                'total' => $adults + $children + $teenagers,
                'has_age_breakdown' => true,
            ];
        }

        $total = max(1, (int) ($this->quantity ?? 1));

        return [
            'adults' => $total,
            'children' => 0,
            'teenagers' => 0,
            'total' => $total,
            'has_age_breakdown' => false,
        ];
    }

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
        
        $template = "ご予約依頼 : [DOSSIER_REF] / [LEAD_NAME]"; 
        if ($productSupplier && !empty($productSupplier->email_subject)) {
            $template = $productSupplier->email_subject;
        }

        $folder = $this->folder;
        $dossierRef = $folder ? $folder->folder_name : 'N/A';
        $leadName = $folder ? $folder->lead_traveler_name : 'N/A';
        $datePresta = $this->service_date ? $this->service_date->format('d/m/Y') : __('Non définie');
        $datePrestaJp = $this->service_date ? $this->service_date->format('Y年m月d日') : __('Non définie');
        $quantite = $this->quantity ?? 1;

        $writerName = auth()->check() ? auth()->user()->name : __('L\'équipe Takada');
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
        $templateData = "ご担当者様"; 
        
        if ($productSupplier && !empty($productSupplier->fax_header)) {
            $templateData = is_array($productSupplier->fax_header) ? ($productSupplier->fax_header['to_contact_name'] ?? 'ご担当者様') : $productSupplier->fax_header;
        }

        $folder = $this->folder;
        $dossierRef = $folder ? $folder->folder_name : 'N/A';
        $leadName = $folder ? $folder->lead_traveler_name : 'N/A';
        $datePresta = $this->service_date ? $this->service_date->format('d/m/Y') : __('Non définie');
        $datePrestaJp = $this->service_date ? $this->service_date->format('Y年m月d日') : __('Non définie');
        $quantite = $this->quantity ?? 1;

        $writerName = auth()->check() ? auth()->user()->name : __('L\'équipe Takada');
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

        $emailRendered = __('Aucun modèle d\'e-mail n\'a été configuré pour ce fournisseur.');

        if ($productSupplier && !empty($productSupplier->email_template)) {
            $emailRendered = $productSupplier->email_template;
        } else {
            return $emailRendered;
        }

        $folder = $this->folder;

        $dossierRef = $folder ? $folder->folder_name : 'N/A';
        $leadName = $folder ? $folder->lead_traveler_name : 'N/A';
        $datePresta = $this->service_date ? $this->service_date->format('d/m/Y') : __('Non définie');
        $datePrestaJp = $this->service_date ? $this->service_date->format('Y年m月d日') : __('Non définie');
        $quantite = $this->quantity ?? 1;
        
        $writerName = auth()->check() ? auth()->user()->name : __('L\'équipe Takada');
        $targetSupplier = $this->getTargetSupplier();
        $supplierContact = ($targetSupplier && $targetSupplier->contact_name) ? $targetSupplier->contact_name : 'Partenaire';

        $selectedOpts = is_string($this->selected_options) ? json_decode($this->selected_options, true) : $this->selected_options;

        $paxAdults = 0;
        $paxChildren = 0;
        $paxTeenagers = 0;
        $childLimit = $product ? ($product->child_age_limit ?? 11) : 11;
        $teenLimit = $product?->teen_age_limit;
        $ageCalcDate = $this->service_date ? Carbon::parse($this->service_date) : Carbon::now();

        $passagersText = "";
        if ($folder && $folder->folderPassengers && $folder->folderPassengers->isNotEmpty()) {
            foreach ($folder->folderPassengers as $index => $passenger) {
                $num = $index + 1;
                $birthStr = $passenger->birth_date ? Carbon::parse($passenger->birth_date)->format('d/m/Y') : __('Inconnue');
                
                if ($passenger->birth_date) {
                    $ageNum = Carbon::parse($passenger->birth_date)->diffInYears($ageCalcDate);
                    $ageStr = $ageNum . ' ' . __('ans');
                    
                    if ($ageNum <= $childLimit) {
                        $paxChildren++;
                    } elseif ($teenLimit !== null && $ageNum <= $teenLimit) {
                        $paxTeenagers++;
                    } else {
                        $paxAdults++;
                    }
                } else {
                    $ageStr = __('Âge inconnu');
                    $paxAdults++;
                }

                $passagersText .= "{$num}. {$passenger->last_name} {$passenger->first_name} ({$passenger->nationality}) - Né(e) le {$birthStr} [{$ageStr}]\n";
            }
        } else {
            $passagersText = __('Aucun passager enregistré.');
            $paxAdults = $quantite;
        }

        // Pour les produits qui utilisent le sélecteur adulte/enfant, les valeurs
        // saisies lors de l'ajout de la prestation sont prioritaires sur le calcul
        // global du dossier. Les anciens enregistrements restent inchangés.
        $ageSelector = is_array($this->custom_values['_age_selector'] ?? null)
            ? $this->custom_values['_age_selector']
            : null;

        if ($product?->enable_age_selector && $ageSelector) {
            $paxAdults = max(0, (int) ($ageSelector['adults'] ?? $paxAdults));
            $paxChildren = max(0, (int) ($ageSelector['children'] ?? $paxChildren));
            $paxTeenagers = max(0, (int) ($ageSelector['teenagers'] ?? $paxTeenagers));
        }

        if ($product && preg_match_all('/\[IF_OPTION:([^\]]+)\](.*?)\[\/IF_OPTION\]/is', $emailRendered, $matches)) {
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
            '[PAX_TEENAGERS]' => $paxTeenagers,
            '[OPTION_NAME]' => $optionName,
            '[LISTE_PASSAGERS]' => trim($passagersText),
            '[NOM_AGENT]' => $writerName,
            '[CONTACT_FOURNISSEUR]' => $supplierContact,
        ];

        $emailRendered = str_replace(array_keys($replacements), array_values($replacements), $emailRendered);

        if (is_array($this->custom_values)) {
            foreach ($this->custom_values as $key => $val) {
                if (str_starts_with((string) $key, '_')) {
                    continue;
                }

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

        if ($product && preg_match_all('/\[OPTION:([^\]]+)\]/', $emailRendered, $matches)) {
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

    public function syncGoogleCalendar()
    {
        Log::info("CALENDAR : ----- TENTATIVE DE SYNCHRONISATION -----");

        if ($this->exists) {
            $freshEventId = DB::table('folder_items')->where('id', $this->id)->value('google_calendar_event_id');
            if ($freshEventId) {
                $this->google_calendar_event_id = $freshEventId;
            }
        }

        $calendarId = config('services.google_calendar.calendar_id');
        if (!$calendarId) {
            Log::warning("CALENDAR : ARRÊT -> L'ID du calendrier (GOOGLE_CALENDAR_ID) est introuvable. Pense à faire php artisan config:clear");
            return;
        }

        $keyFilePath = storage_path('app/google-credentials.json');
        if (!file_exists($keyFilePath)) {
            Log::warning("CALENDAR : ARRÊT -> Le fichier JSON Google est introuvable au chemin : {$keyFilePath}");
            return;
        }

        $folder = $this->folder;
        $product = $this->product;
        
        $hasDate = $this->service_date ? 'OUI' : 'NON';
        $hasProduct = $product ? 'OUI' : 'NON';
        $delay = $product ? ($product->days_before_opening ?? 'NULL') : 'NULL';
        $status = $folder->status ?? 'INCONNU';
        $itemStatusName = $this->itemStatus ? mb_strtolower(trim($this->itemStatus->name), 'UTF-8') : 'inconnu';

        Log::info("CALENDAR : Check des données -> Date:{$hasDate} | Produit:{$hasProduct} | Délai d'achat:{$delay} | Statut dossier:{$status} | Statut prestation:{$itemStatusName}");

        $stopItemStatuses = ['confirmé', 'confirme', 'annulé', 'annule', 'pas de disponibilité', 'pas de disponibilite', 'indisponible', 'en cours de traitement'];

        if (!$this->service_date || !$product || $product->days_before_opening === null || ($folder && in_array($folder->status, ['cancelled', 'completed'])) || in_array($itemStatusName, $stopItemStatuses)) {
            Log::info("CALENDAR : ARRÊT -> Condition non remplie (Pas de date/délai, ou prestation en cours/confirmée/annulée/indisponible). Appel suppression.");
            $this->deleteGoogleCalendarEvent();
            return;
        }

        Log::info("CALENDAR : Toutes les conditions sont valides. Requête API en cours...");

        try {
            $client = new \Google\Client();
            $client->setAuthConfig($keyFilePath);
            $client->addScope(\Google\Service\Calendar::CALENDAR);
            $service = new \Google\Service\Calendar($client);

            $openDate = Carbon::parse($this->service_date)->subDays($product->days_before_opening)->startOfDay();
            if ($openDate->lessThan(Carbon::today())) {
                $openDate = Carbon::today();
            }
            
            $folderName = $folder ? $folder->folder_name : 'N/A';
            $productName = $product->name;
            $supplierName = $this->getTargetSupplier()?->name ?? 'Aucun fournisseur';
            $qty = $this->quantity ?? 1;

            $event = new \Google\Service\Calendar\Event([
                'summary' => "🛒 ACHAT : {$productName} ({$folderName})",
                'description' => "Dossier : {$folderName}\nPrestation : {$productName}\nFournisseur : {$supplierName}\nQuantité/Pax : {$qty}\nDate de la prestation : {$this->service_date->format('d/m/Y')}\n\nOuvrir le dossier : " . route('filament.admin.resources.folders.edit', ['record' => $this->folder_id]),
                'start' => ['date' => $openDate->format('Y-m-d')],
                'end' => ['date' => $openDate->copy()->addDay()->format('Y-m-d')],
            ]);

            if ($this->google_calendar_event_id) {
                try {
                    $service->events->patch($calendarId, $this->google_calendar_event_id, $event);
                    Log::info("CALENDAR : SUCCÈS -> Événement mis à jour.");
                } catch (\Exception $e) {
                    $created = $service->events->insert($calendarId, $event);
                    $this->google_calendar_event_id = $created->id; 
                    if ($this->exists) {
                        DB::table('folder_items')->where('id', $this->id)->update(['google_calendar_event_id' => $created->id]);
                    }
                    Log::info("CALENDAR : SUCCÈS -> Événement recréé (l'ancien était introuvable).");
                }
            } else {
                $created = $service->events->insert($calendarId, $event);
                $this->google_calendar_event_id = $created->id;
                if ($this->exists) {
                    DB::table('folder_items')->where('id', $this->id)->update(['google_calendar_event_id' => $created->id]);
                }
                Log::info("CALENDAR : SUCCÈS -> Nouvel événement créé ! ID: " . $created->id);
            }
        } catch (\Exception $e) {
            Log::error("CALENDAR ERREUR GOOGLE API : " . $e->getMessage());
        }
    }

    public function deleteGoogleCalendarEvent()
    {
        if ($this->exists) {
            $freshEventId = DB::table('folder_items')->where('id', $this->id)->value('google_calendar_event_id');
            if ($freshEventId) {
                $this->google_calendar_event_id = $freshEventId;
            }
        }

        $calendarId = config('services.google_calendar.calendar_id');
        $keyFilePath = storage_path('app/google-credentials.json');
        
        if (!$calendarId || !file_exists($keyFilePath)) {
            return;
        }

        try {
            $client = new \Google\Client();
            $client->setAuthConfig($keyFilePath);
            $client->addScope(\Google\Service\Calendar::CALENDAR);
            $service = new \Google\Service\Calendar($client);

            if ($this->google_calendar_event_id) {
                try {
                    $service->events->delete($calendarId, $this->google_calendar_event_id);
                    Log::info("CALENDAR : Événement principal supprimé.");
                } catch (\Google\Service\Exception $e) {
                    // 404 / 410 : l'événement n'existe déjà plus, ce n'est pas une erreur.
                    if (in_array($e->getCode(), [404, 410], true)) {
                        Log::info("CALENDAR : Événement principal déjà supprimé.");
                    } else {
                        Log::warning("CALENDAR Erreur suppression ID officiel : " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    Log::warning("CALENDAR Erreur suppression ID officiel : " . $e->getMessage());
                }
            }

            $folderName = $this->folder ? $this->folder->folder_name : 'N/A';
            $productName = $this->product ? $this->product->name : '';
            
            if ($productName) {
                $searchQuery = "🛒 ACHAT : {$productName} ({$folderName})";
                
                $optParams = [
                    'q' => $searchQuery,
                    'timeMin' => now()->subYears(1)->toRfc3339String(),
                ];

                $results = $service->events->listEvents($calendarId, $optParams);

                foreach ($results->getItems() as $event) {
                    try {
                        $service->events->delete($calendarId, $event->getId());
                        Log::info("CALENDAR : Doublon/Fantôme supprimé (ID: " . $event->getId() . ")");
                    } catch (\Exception $e) {
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error("CALENDAR Erreur suppression globale : " . $e->getMessage());
        }

        if ($this->exists) {
            DB::table('folder_items')->where('id', $this->id)->update(['google_calendar_event_id' => null]);
        }
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
            if ($item->wasChanged('item_status_id')
                && (int) $item->product?->category_id === 4
                && $item->guide_mission_id
                && $item->isCancelled()) {
                try {
                    \App\Jobs\CancelGuideMission::dispatch($item->id)->afterCommit();
                } catch (\Throwable $exception) {
                    Log::channel('guide_integration')->error('Impossible de mettre l’annulation de mission en file.', ['folder_item_id' => $item->id, 'error' => $exception->getMessage()]);
                }
            }

            // Le total du dossier dépend du statut, du prix et de la visibilité de chaque prestation.
            if ($item->wasChanged(['item_status_id', 'total_price', 'is_internal']) && $item->folder) {
                $item->folder->recalculateBillableTotal();
            }

            if ($item->wasChanged([
                'service_date', 'product_id', 'product_option_id', 'quantity',
                'pax_adults', 'pax_children', 'pax_teenagers', 'custom_values',
                'purchase_total_price',
            ]) && in_array($item->folder?->status, ['confirmed', 'completed'], true)
                && (int) $item->product?->category_id === 4) {
                try {
                    \App\Jobs\SyncGuideMission::dispatch($item->id)->afterCommit();
                } catch (\Throwable $exception) {
                    Log::channel('guide_integration')->error('Impossible de remettre la mission en file.', ['folder_item_id' => $item->id, 'error' => $exception->getMessage()]);
                }
            }

            static $processedUpdates = [];

            if ($item->wasChanged()) {
                $changes = $item->getChanges();
                unset($changes['updated_at']);

                if (!empty($changes)) {
                    $fingerprint = $item->id . '_' . md5(json_encode($changes));
                    
                    if (isset($processedUpdates[$fingerprint])) return;
                    $processedUpdates[$fingerprint] = true;

                    if ($item->wasChanged(['service_date', 'quantity', 'product_id', 'supplier_id', 'item_status_id'])) {
                        try { 
                            $item->syncGoogleCalendar(); 
                        } catch (\Exception $e) {
                            Log::error("CALENDAR Erreur d'appel hook updated : " . $e->getMessage());
                        }
                    }

                    $productName = $item->title ?: ($item->product ? $item->product->name : 'Une prestation');
                    if ($item->service_date) {
                        $productName .= ' (du ' . Carbon::parse($item->service_date)->format('d/m/Y') . ')';
                    } elseif ($item->id) {
                        $productName .= ' (Réf: #' . $item->id . ')';
                    }

                    $changesText = [];
                    $structuredChanges = [];
                    
                    $labels = [
                        'product_id' => __('Produit'),
                        'product_option_id' => __('Option principale'),
                        'item_status_id' => __('Statut de la prestation'),
                        'supplier_id' => __('Fournisseur'),
                        'service_date' => __('Date de service'),
                        'quantity' => __('Quantité'),
                        'pax_adults' => __("Nombre d'adultes"),
                        'pax_children' => __("Nombre d'enfants"),
                        'pax_teenagers' => __("Nombre d'adolescents"),
                        'purchase_unit_price' => __('Prix d\'achat unitaire'),
                        'purchase_total_price' => __('Prix d\'achat total'),
                        'unit_price' => __('Prix de vente unitaire'),
                        'total_price' => __('Prix de vente total'),
                        'selected_options' => __('Options sélectionnées'),
                        'invoice_received_at' => __('Date de réception facture'),
                        'title' => __('Titre'),
                        'is_internal' => __('Prestation interne'),
                        'label_exported_at' => __("Date d'export de l'étiquette"),
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
                                foreach (self::historyCustomValueChanges(
                                    (string) $k,
                                    $oldCustom[$k] ?? null,
                                    $newCustom[$k] ?? null,
                                ) as $change) {
                                    $changesText[] = "• {$change['label']} : '{$change['old']}' ➔ '{$change['new']}'";
                                    $structuredChanges[] = $change;
                                }
                            }
                            continue;
                        }

                        if (!array_key_exists($key, $labels)) continue;

                        if ($key === 'item_status_id') {
                            $oldStatus = $oldValue ? (\App\Models\ItemStatus::find($oldValue)?->name ?? 'Inconnu') : 'Aucun';
                            $newStatus = $newValue ? (\App\Models\ItemStatus::find($newValue)?->name ?? 'Inconnu') : 'Aucun';
                            $changesText[] = "• {$labels[$key]} : '{$oldStatus}' ➔ '{$newStatus}'";
                            $structuredChanges[] = ['field' => $key, 'label' => $labels[$key], 'old' => $oldStatus, 'new' => $newStatus];
                            continue;
                        }
                        
                        if ($key === 'supplier_id') {
                            $oldSupp = $oldValue ? (\App\Models\Supplier::find($oldValue)?->name ?? 'Par défaut') : 'Par défaut';
                            $newSupp = $newValue ? (\App\Models\Supplier::find($newValue)?->name ?? 'Par défaut') : 'Par défaut';
                            $changesText[] = "• {$labels[$key]} : '{$oldSupp}' ➔ '{$newSupp}'";
                            $structuredChanges[] = ['field' => $key, 'label' => $labels[$key], 'old' => $oldSupp, 'new' => $newSupp];
                            continue;
                        }

                        if ($key === 'service_date' || $key === 'invoice_received_at') {
                            $oldDate = $oldValue ? Carbon::parse($oldValue)->format('d/m/Y') : 'Non renseignée';
                            $newDate = $newValue ? Carbon::parse($newValue)->format('d/m/Y') : 'Vide';
                            $changesText[] = "• {$labels[$key]} : '{$oldDate}' ➔ '{$newDate}'";
                            $structuredChanges[] = ['field' => $key, 'label' => $labels[$key], 'old' => $oldDate, 'new' => $newDate];
                            continue;
                        }

                        if ($key === 'selected_options') {
                            $oldOptions = self::historySelectedOptions($oldValue);
                            $newOptions = self::historySelectedOptions($newValue);

                            if ($oldOptions !== $newOptions) {
                                $changesText[] = "• {$labels[$key]} : '{$oldOptions}' ➔ '{$newOptions}'";
                                $structuredChanges[] = [
                                    'field' => $key,
                                    'label' => $labels[$key],
                                    'old' => $oldOptions,
                                    'new' => $newOptions,
                                ];
                            }
                            continue;
                        }

                        if ($key === 'product_id' || $key === 'product_option_id') {
                            $model = $key === 'product_id' ? Product::class : ProductOption::class;
                            $oldString = $oldValue ? ($model::find($oldValue)?->name ?? __('Élément supprimé')) : __('Non renseigné');
                            $newString = $newValue ? ($model::find($newValue)?->name ?? __('Élément supprimé')) : __('Vide');
                            $changesText[] = "• {$labels[$key]} : '{$oldString}' ➔ '{$newString}'";
                            $structuredChanges[] = ['field' => $key, 'label' => $labels[$key], 'old' => $oldString, 'new' => $newString];
                            continue;
                        }

                        $oldString = is_array($oldValue) ? json_encode($oldValue) : ($oldValue !== null && $oldValue !== '' ? (string)$oldValue : 'Non renseigné');
                        $newString = is_array($newValue) ? json_encode($newValue) : ($newValue !== null && $newValue !== '' ? (string)$newValue : 'Vide');
                        $changesText[] = "• {$labels[$key]} : '{$oldString}' ➔ '{$newString}'";
                        $structuredChanges[] = ['field' => $key, 'label' => $labels[$key], 'old' => $oldString, 'new' => $newString];
                    }

                    if (!empty($changesText)) {
                        $summary = __("La prestation ':name' a été modifiée :", ['name' => $productName]) . "\n" . implode("\n", $changesText);
                        FolderHistory::logConsolidated(
                            $item->folder_id,
                            __('Mise à jour Prestation'),
                            $summary,
                            'service',
                            $structuredChanges,
                            ['item_id' => $item->id, 'service_label' => $productName, 'event' => 'updated'],
                        );
                    }
                }
            }
        });

        static::created(function ($item) {
            static $processedCreations = [];
            if (isset($processedCreations[$item->id])) return;
            $processedCreations[$item->id] = true;

            $item->folder?->recalculateBillableTotal();

            if (in_array($item->folder?->status, ['confirmed', 'completed'], true)
                && (int) $item->product?->category_id === 4) {
                try {
                    \App\Jobs\SyncGuideMission::dispatch($item->id)->afterCommit();
                } catch (\Throwable $exception) {
                    Log::channel('guide_integration')->error('Impossible de mettre la mission en file.', ['folder_item_id' => $item->id, 'error' => $exception->getMessage()]);
                }
            }

            try { 
                $item->syncGoogleCalendar(); 
            } catch (\Exception $e) {
                Log::error("CALENDAR Erreur d'appel hook created : " . $e->getMessage());
            }

            $productName = $item->title ?: ($item->product ? $item->product->name : 'Une prestation');
            if ($item->service_date) {
                $productName .= ' (du ' . Carbon::parse($item->service_date)->format('d/m/Y') . ')';
            }

            FolderHistory::logConsolidated(
                $item->folder_id,
                __('Ajout Prestation'),
                __("La prestation ':name' a été ajoutée au dossier.", ['name' => $productName]),
                'service',
                [],
                ['item_id' => $item->id, 'event' => 'created'],
            );
        });

        static::deleted(function ($item) {
            $item->folder?->recalculateBillableTotal();

            try { 
                $item->deleteGoogleCalendarEvent(); 
            } catch (\Exception $e) {
                Log::error("CALENDAR Erreur d'appel hook deleted : " . $e->getMessage());
            }

            try {
                \App\Models\FolderTask::where('action_code', "manage_item_{$item->id}")
                    ->orWhere('action_code', "item_alert_{$item->id}")
                    ->orWhere('action_code', "invoice_missing_{$item->id}")
                    ->orWhere('action_code', "booking_open_{$item->id}")
                    ->orWhere('action_code', "missing_purchase_price_{$item->id}")
                    ->delete();
            } catch (\Exception $e) {
                Log::error("TÂCHES Erreur suppression hook deleted : " . $e->getMessage());
            }

            $productName = $item->title ?: ($item->product ? $item->product->name : 'Une prestation');
            if ($item->service_date) {
                $productName .= ' (du ' . Carbon::parse($item->service_date)->format('d/m/Y') . ')';
            }

            FolderHistory::logConsolidated(
                $item->folder_id,
                __('Suppression Prestation'),
                __("La prestation ':name' a été retirée du dossier.", ['name' => $productName]),
                'service',
                [],
                ['item_id' => $item->id, 'event' => 'deleted'],
            );
        });
    }

    /**
     * Convert stored dynamic values into concise, human-readable history lines.
     * Internal fields and JSON serialisation differences are deliberately ignored.
     */
    private static function historyCustomValueChanges(string $key, mixed $oldValue, mixed $newValue): array
    {
        if ($key === '_age_selector') {
            return [];
        }

        if ($key === 'transport_routes') {
            return self::formatTransportRouteHistoryChanges($oldValue, $newValue);
        }

        $old = self::historyValue($oldValue);
        $new = self::historyValue($newValue);

        if ($old === $new) {
            return [];
        }

        $label = __('Information :name', ['name' => str($key)->replace('_', ' ')->headline()]);

        return [[
            'field' => "custom_values.{$key}",
            'label' => $label,
            'old' => $old,
            'new' => $new,
        ]];
    }

    public static function formatTransportRouteHistoryChanges(mixed $oldValue, mixed $newValue): array
    {
        if (is_string($oldValue)) {
            $oldValue = json_decode($oldValue, true);
        }

        if (is_string($newValue)) {
            $newValue = json_decode($newValue, true);
        }

        $oldRoutes = is_array($oldValue) ? array_values($oldValue) : [];
        $newRoutes = is_array($newValue) ? array_values($newValue) : [];
        $changes = [];
        $maxRoutes = max(count($oldRoutes), count($newRoutes));

        $labels = [
            'departure_station' => __('Gare de départ'),
            'arrival_station' => __('Gare d’arrivée'),
            'departure_date' => __('Date du trajet'),
            'departure_time' => __('Heure de départ'),
            'arrival_time' => __('Heure d’arrivée'),
            'train_number' => __('N° de train / bus'),
            'specific_requests' => __('Demandes spécifiques/indication complémentaire'),
            'option_id' => __('Classe'),
            'pax_count' => __('Nombre de passagers'),
            'supplier_id' => __('Fournisseur'),
            'purchase_price' => __('Prix d’achat'),
        ];

        for ($index = 0; $index < $maxRoutes; $index++) {
            $oldRoute = is_array($oldRoutes[$index] ?? null) ? $oldRoutes[$index] : null;
            $newRoute = is_array($newRoutes[$index] ?? null) ? $newRoutes[$index] : null;

            if ($oldRoute === null || $newRoute === null) {
                $route = $newRoute ?? $oldRoute ?? [];
                $label = __('Trajet :route', ['route' => self::historyRouteLabel($route, $index)]);
                $changes[] = [
                    'field' => "custom_values.transport_routes.{$index}",
                    'label' => $label,
                    'old' => $oldRoute === null ? __('Non défini') : __('Trajet supprimé'),
                    'new' => $newRoute === null ? __('Trajet supprimé') : __('Trajet ajouté'),
                ];

                continue;
            }

            foreach ($labels as $field => $label) {
                $old = self::historyRouteValue($field, $oldRoute[$field] ?? null);
                $new = self::historyRouteValue($field, $newRoute[$field] ?? null);

                if ($old === $new) {
                    continue;
                }

                $changes[] = [
                    'field' => "custom_values.transport_routes.{$index}.{$field}",
                    'label' => __('Trajet :route — :field', [
                        'route' => self::historyRouteLabel($newRoute, $index),
                        'field' => $label,
                    ]),
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        return $changes;
    }

    private static function historyRouteLabel(array $route, int $index): string
    {
        $from = self::historyValue($route['departure_station'] ?? null);
        $to = self::historyValue($route['arrival_station'] ?? null);
        $date = self::historyRouteValue('departure_date', $route['departure_date'] ?? null);

        return "{$from} → {$to}" . ($date !== __('Non défini') ? " ({$date})" : ' #' . ($index + 1));
    }

    private static function historySelectedOptions(mixed $value): string
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value) || $value === []) {
            return __('Aucune option');
        }

        $options = collect($value)
            ->filter(fn ($option): bool => is_array($option) && filled($option['product_option_id'] ?? null))
            ->map(function (array $option): string {
                $name = ProductOption::find($option['product_option_id'])?->name ?? __('Option supprimée');
                $quantity = max(1, (int) ($option['quantity'] ?? 1));

                return $quantity > 1 ? "{$name} × {$quantity}" : $name;
            })
            ->values();

        return $options->isNotEmpty() ? $options->implode(', ') : __('Aucune option');
    }

    private static function historyRouteValue(string $field, mixed $value): string
    {
        if ($field === 'option_id') {
            return $value ? (ProductOption::find($value)?->name ?? __('Option supprimée')) : __('Non défini');
        }

        if ($field === 'supplier_id') {
            return $value ? (Supplier::find($value)?->name ?? __('Fournisseur supprimé')) : __('Non défini');
        }

        if ($field === 'departure_date' && filled($value)) {
            try {
                return Carbon::parse($value)->format('d/m/Y');
            } catch (\Throwable) {
                // Keep the original value if an older record contains an invalid date.
            }
        }

        if ($field === 'purchase_price' && filled($value)) {
            return number_format((float) $value, 0, ',', ' ') . ' ¥';
        }

        return self::historyValue($value);
    }

    private static function historyValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('Non défini');
        }

        if (is_bool($value)) {
            return $value ? __('Oui') : __('Non');
        }

        if (is_array($value)) {
            $values = collect($value)
                ->filter(fn ($item) => !is_array($item) && $item !== null && $item !== '')
                ->map(fn ($item) => self::historyValue($item))
                ->values();

            return $values->isNotEmpty()
                ? $values->implode(', ')
                : __('Données mises à jour');
        }

        return (string) $value;
    }

    public function folder(): BelongsTo { return $this->belongsTo(Folder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function productOption(): BelongsTo { return $this->belongsTo(ProductOption::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class, 'supplier_id'); }
    public function itemStatus(): BelongsTo { return $this->belongsTo(ItemStatus::class); }
}
