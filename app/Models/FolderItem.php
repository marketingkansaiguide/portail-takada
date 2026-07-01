<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class FolderItem extends Model
{
    protected $fillable = [
        'folder_id', 'product_id', 'product_option_id', 'item_status_id',
        'service_date', 'quantity', 'unit_price', 'total_price', 'custom_values', 
        'selected_options',
    ];

    protected $casts = [
        'service_date' => 'date', 'quantity' => 'integer',
        'unit_price' => 'integer', 'total_price' => 'integer', 
        'custom_values' => 'array', 'selected_options' => 'array',
    ];

    /**
     * Moteur de parsing de l'OBJET de l'e-mail
     */
    public function parseSupplierEmailSubject(): string
    {
        $product = $this->product;
        
        $template = ($product && !empty($product->supplier_email_subject)) 
            ? $product->supplier_email_subject 
            : "ご予約依頼 : [DOSSIER_REF] / [LEAD_NAME]";

        $folder = $this->folder;
        $dossierRef = $folder ? $folder->reference : 'N/A';
        $leadName = $folder ? $folder->lead_traveler_name : 'N/A';
        $datePresta = $this->service_date ? $this->service_date->format('d/m/Y') : 'Non définie';
        $datePrestaJp = $this->service_date ? $this->service_date->format('Y年m月d日') : 'Non définie';
        $quantite = $this->quantity ?? 1;

        $writerName = auth()->check() ? auth()->user()->name : 'L\'équipe Takada';
        $supplierContact = ($product && $product->supplier && $product->supplier->contact_name) 
            ? $product->supplier->contact_name 
            : 'Partenaire';

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

    /**
     * Moteur de parsing des Shortcodes & Conditions : Génère l'e-mail formaté pour le fournisseur
     */
    public function parseSupplierEmail(): string
    {
        $product = $this->product;
        if (!$product || empty($product->supplier_email_template)) {
            return "Aucun modèle d'e-mail n'a été configuré pour ce produit.";
        }

        $emailRendered = $product->supplier_email_template;
        $folder = $this->folder;

        // --- 1. PRÉPARATION DES VARIABLES GÉNÉRALES ---
        $dossierRef = $folder ? $folder->reference : 'N/A';
        $leadName = $folder ? $folder->lead_traveler_name : 'N/A';
        $datePresta = $this->service_date ? $this->service_date->format('d/m/Y') : 'Non définie';
        $datePrestaJp = $this->service_date ? $this->service_date->format('Y年m月d日') : 'Non définie';
        $quantite = $this->quantity ?? 1;
        
        $writerName = auth()->check() ? auth()->user()->name : 'L\'équipe Takada';
        $supplierContact = ($product->supplier && $product->supplier->contact_name) 
            ? $product->supplier->contact_name 
            : 'Partenaire';

        $selectedOpts = is_string($this->selected_options) ? json_decode($this->selected_options, true) : $this->selected_options;

        // --- 2. CALCUL ANTICIPÉ DES PASSAGERS ---
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

        // --- 3. MOTEUR DE CONDITIONS LOGIQUES ---
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

        // --- 4. RÉSOLUTION DES SHORTCODES DE TEXTE ---
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

        // --- 5. RÉSOUUTION AVANCÉE DES FORMULAIRES DYNAMIQUES [CUSTOM:cle] ---
        if (is_array($this->custom_values)) {
            foreach ($this->custom_values as $key => $val) {
                if (is_bool($val)) {
                    $userValue = $val ? __('Oui') : __('Non');
                } elseif (empty($val) && $val !== 0 && $val !== '0') {
                    $userValue = '';
                } else {
                    $userValue = (string) $val;
                }
                
                $emailRendered = str_replace("[CUSTOM:{$key}]", $userValue, $emailRendered);
            }
        }

        // --- 6. Remplacement des shortcodes d'options [OPTION:cle] ---
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

    protected static function booted()
    {
        static::updated(function ($item) {
            static $processedUpdates = [];

            if ($item->wasChanged()) {
                $changes = $item->getChanges();
                unset($changes['updated_at']);

                if (!empty($changes)) {
                    $fingerprint = $item->id . '_' . md5(json_encode($changes));
                    
                    if (isset($processedUpdates[$fingerprint])) return;
                    $processedUpdates[$fingerprint] = true;

                    $productName = $item->product ? $item->product->name : 'Une prestation';
                    $changesText = [];
                    
                    $labels = [
                        'item_status_id' => 'Statut de la prestation',
                        'service_date' => 'Date de service',
                        'quantity' => 'Quantité',
                        'unit_price' => 'Prix unitaire',
                        'total_price' => 'Prix total',
                        'selected_options' => 'Options sélectionnées',
                    ];

                    foreach ($changes as $key => $newValue) {
                        $oldValue = $item->getOriginal($key);

                        // 💡 LOGIQUE DÉTAILLÉE POUR LES CHAMPS PERSONNALISÉS (CUSTOM VALUES)
                        if ($key === 'custom_values') {
                            $oldCustom = is_string($oldValue) ? json_decode($oldValue, true) : (is_array($oldValue) ? $oldValue : []);
                            $newCustom = is_string($newValue) ? json_decode($newValue, true) : (is_array($newValue) ? $newValue : []);

                            $oldCustom = $oldCustom ?: [];
                            $newCustom = $newCustom ?: [];

                            // Compare les deux tableaux pour trouver toutes les clés impactées
                            $allKeys = array_unique(array_merge(array_keys($oldCustom), array_keys($newCustom)));

                            foreach ($allKeys as $k) {
                                // Formate les booléens (Toggle) en Oui/Non
                                $oldV = isset($oldCustom[$k]) ? (is_bool($oldCustom[$k]) ? ($oldCustom[$k] ? 'Oui' : 'Non') : (string)$oldCustom[$k]) : 'Vide';
                                $newV = isset($newCustom[$k]) ? (is_bool($newCustom[$k]) ? ($newCustom[$k] ? 'Oui' : 'Non') : (string)$newCustom[$k]) : 'Vide';

                                if ($oldV === '') $oldV = 'Vide';
                                if ($newV === '') $newV = 'Vide';

                                if ($oldV !== $newV) {
                                    $changesText[] = "• Information '{$k}' : '{$oldV}' ➔ '{$newV}'";
                                }
                            }
                            continue;
                        }

                        // Logique standard pour les autres clés
                        if (!array_key_exists($key, $labels)) continue;

                        if ($key === 'item_status_id') {
                            $oldStatus = $oldValue ? (\App\Models\ItemStatus::find($oldValue)?->name ?? 'Inconnu') : 'Aucun';
                            $newStatus = $newValue ? (\App\Models\ItemStatus::find($newValue)?->name ?? 'Inconnu') : 'Aucun';
                            $changesText[] = "• {$labels[$key]} : '{$oldStatus}' ➔ '{$newStatus}'";
                            continue;
                        }

                        if ($key === 'service_date') {
                            $oldDate = $oldValue ? Carbon::parse($oldValue)->format('d/m/Y') : 'Non renseignée';
                            $newDate = $newValue ? Carbon::parse($newValue)->format('d/m/Y') : 'Vide';
                            $changesText[] = "• {$labels[$key]} : '{$oldDate}' ➔ '{$newDate}'";
                            continue;
                        }

                        if ($key === 'selected_options') {
                            $changesText[] = "• Les 'Options sélectionnées' ont été modifiées.";
                            continue;
                        }

                        $oldString = $oldValue !== null && $oldValue !== '' ? (string)$oldValue : 'Non renseigné';
                        $newString = $newValue !== null && $newValue !== '' ? (string)$newValue : 'Vide';
                        $changesText[] = "• {$labels[$key]} : '{$oldString}' ➔ '{$newString}'";
                    }

                    if (!empty($changesText)) {
                        $summary = "La prestation '{$productName}' a été modifiée :\n" . implode("\n", $changesText);

                        \App\Models\FolderHistory::create([
                            'folder_id' => $item->folder_id,
                            'user_id' => auth()->id(),
                            'action' => 'Mise à jour Prestation',
                            'changes_payload' => ['summary' => $summary]
                        ]);
                    }
                }
            }
        });

        static::created(function ($item) {
            static $processedCreations = [];
            if (isset($processedCreations[$item->id])) return;
            $processedCreations[$item->id] = true;

            $productName = $item->product ? $item->product->name : 'Une prestation';
            \App\Models\FolderHistory::create([
                'folder_id' => $item->folder_id,
                'user_id' => auth()->id(),
                'action' => 'Ajout Prestation',
                'changes_payload' => ['summary' => "La prestation '{$productName}' a été ajoutée au dossier."]
            ]);
        });

        static::deleted(function ($item) {
            $productName = $item->product ? $item->product->name : 'Une prestation';
            \App\Models\FolderHistory::create([
                'folder_id' => $item->folder_id,
                'user_id' => auth()->id(),
                'action' => 'Suppression Prestation',
                'changes_payload' => ['summary' => "La prestation '{$productName}' a été retirée du dossier."]
            ]);
        });
    }

    public function folder(): BelongsTo { return $this->belongsTo(Folder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function productOption(): BelongsTo { return $this->belongsTo(ProductOption::class); }
    public function itemStatus(): BelongsTo { return $this->belongsTo(ItemStatus::class); }
}