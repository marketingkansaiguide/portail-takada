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
    ];

    protected $casts = [
        'service_date' => 'date', 'quantity' => 'integer',
        'unit_price' => 'integer', 'total_price' => 'integer', 'custom_values' => 'array',
    ];

    /**
     * Moteur de parsing des Shortcodes : Génère l'e-mail formaté pour le fournisseur
     */
    public function parseSupplierEmail(): string
    {
        $product = $this->product;
        if (!$product || empty($product->supplier_email_template)) {
            return "Aucun modèle d'e-mail n'a été configuré pour ce produit.";
        }

        $template = $product->supplier_email_template;
        $folder = $this->folder;

        // 1. Préparation des variables générales avec les dates
        $dossierRef = $folder ? $folder->reference : 'N/A';
        $leadName = $folder ? $folder->lead_traveler_name : 'N/A';
        
        $datePresta = $this->service_date ? $this->service_date->format('d/m/Y') : 'Non définie';
        $datePrestaJp = $this->service_date ? $this->service_date->format('Y年m月d日') : 'Non définie'; // 🇯🇵 Date au format japonais
        
        $quantite = $this->quantity ?? 1;
        $optionName = $this->productOption ? $this->productOption->name : 'Aucune option';

        $writerName = auth()->check() ? auth()->user()->name : 'L\'équipe Takada';
        $supplierContact = ($product->supplier && $product->supplier->contact_name) 
            ? $product->supplier->contact_name 
            : 'Partenaire';

        // 2. Construction de la liste des passagers
        $passagersText = "";
        if ($folder && $folder->folderPassengers && $folder->folderPassengers->isNotEmpty()) {
            foreach ($folder->folderPassengers as $index => $passenger) {
                $num = $index + 1;
                $birth = $passenger->birth_date ? Carbon::parse($passenger->birth_date)->format('d/m/Y') : 'Inconnue';
                $age = $passenger->birth_date ? Carbon::parse($passenger->birth_date)->age . ' ans' : '';
                
                $passagersText .= "{$num}. {$passenger->last_name} {$passenger->first_name} ({$passenger->nationality}) - Né(e) le {$birth} [{$age}]\n";
            }
        } else {
            $passagersText = "Aucun passager enregistré.";
        }

        // 3. Remplacement des shortcodes fixes principaux
        $replacements = [
            '[DOSSIER_REF]' => $dossierRef,
            '[LEAD_NAME]' => $leadName,
            '[DATE_PRESTA]' => $datePresta,
            '[DATE_PRESTA_JP]' => $datePrestaJp, // 💡 Nouveau shortcode
            '[QUANTITE]' => $quantite,
            '[OPTION_NAME]' => $optionName,
            '[LISTE_PASSAGERS]' => trim($passagersText),
            '[NOM_AGENT]' => $writerName,
            '[CONTACT_FOURNISSEUR]' => $supplierContact,
        ];

        $emailRendered = str_replace(array_keys($replacements), array_values($replacements), $template);

        // 4. Remplacement dynamique des Custom Fields du Produit [CUSTOM:cle_technique]
        if (is_array($this->custom_values)) {
            foreach ($this->custom_values as $key => $val) {
                $userValue = is_array($val) ? implode(', ', $val) : $val;
                $emailRendered = str_replace("[CUSTOM:{$key}]", $userValue, $emailRendered);
            }
        }

        return $emailRendered;
    }

    /**
     * Écoute des événements du modèle pour forcer l'historique du dossier
     */
    protected static function booted()
    {
        // 1. On utilise 'updated' au lieu de 'saved'
        static::updated(function ($item) {
            // 2. Verrou mémoire qui ne vit que le temps de la requête (bloque les doublons de Filament)
            static $processedUpdates = [];

            if ($item->wasChanged()) {
                $changes = $item->getChanges();
                unset($changes['updated_at']);

                if (!empty($changes)) {
                    // Création d'une empreinte unique pour cette modification
                    $fingerprint = $item->id . '_' . md5(json_encode($changes));
                    
                    if (isset($processedUpdates[$fingerprint])) {
                        return;
                    }
                    $processedUpdates[$fingerprint] = true;

                    $productName = $item->product ? $item->product->name : 'Une prestation';
                    $changesText = [];
                    
                    $labels = [
                        'item_status_id' => 'Statut de la prestation',
                        'service_date' => 'Date de service',
                        'quantity' => 'Quantité',
                        'unit_price' => 'Prix unitaire',
                        'total_price' => 'Prix total',
                        'custom_values' => 'Champs personnalisés',
                    ];

                    foreach ($changes as $key => $newValue) {
                        if (!array_key_exists($key, $labels)) continue;

                        $oldValue = $item->getOriginal($key);

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

                        if (is_array($oldValue) || is_array($newValue)) {
                            $changesText[] = "• Les '{$labels[$key]}' ont été mises à jour.";
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
                            'changes_payload' => [
                                'summary' => $summary
                            ]
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
                'changes_payload' => [
                    'summary' => "La prestation '{$productName}' a été ajoutée au dossier."
                ]
            ]);
        });

        static::deleted(function ($item) {
            $productName = $item->product ? $item->product->name : 'Une prestation';
            \App\Models\FolderHistory::create([
                'folder_id' => $item->folder_id,
                'user_id' => auth()->id(),
                'action' => 'Suppression Prestation',
                'changes_payload' => [
                    'summary' => "La prestation '{$productName}' a été retirée du dossier."
                ]
            ]);
        });
    }

    public function folder(): BelongsTo { return $this->belongsTo(Folder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function productOption(): BelongsTo { return $this->belongsTo(ProductOption::class); }
    public function itemStatus(): BelongsTo { return $this->belongsTo(ItemStatus::class); }
}