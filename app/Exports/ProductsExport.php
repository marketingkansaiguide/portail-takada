<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductPeriod;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Export Excel du catalogue produits : une feuille par niveau d'information
 * (fiche produit, options, tarifs, tarifs saisonniers des options), reliées par l'ID du produit.
 */
class ProductsExport implements WithMultipleSheets
{
    private const DAYS = ['mon' => 'Lun', 'tue' => 'Mar', 'wed' => 'Mer', 'thu' => 'Jeu', 'fri' => 'Ven', 'sat' => 'Sam', 'sun' => 'Dim'];

    private const BILLING = ['per_pax' => 'Par pax', 'per_booking' => 'Fixe dossier', 'manual' => 'Quantité libre'];

    private const PRICING = ['per_pax' => 'Par pax', 'global' => 'Forfait global', 'extra_pax' => 'Supplément par pax supplémentaire'];

    private const FIELD_TYPES = [
        'text' => 'Texte court', 'textarea' => 'Texte long', 'number' => 'Nombre', 'date' => 'Date',
        'toggle' => 'Oui/Non', 'select' => 'Liste de choix', 'file' => 'Fichier', 'train_station' => 'Gare de train',
    ];

    /** @var Collection<int, Product> */
    private Collection $products;

    public function __construct(Builder $query)
    {
        $this->products = $query
            ->with([
                'category',
                'clientGroups',
                'productSuppliers.supplier',
                'productOptions.seasonalPrices.productPeriod',
                'productPeriods.productPrices',
                'variantPeriods.productPrices',
            ])
            ->reorder('name')
            ->get();
    }

    public function sheets(): array
    {
        return [
            new ProductsExportSheet('Produits', $this->productHeadings(), $this->productRows()),
            new ProductsExportSheet('Options', $this->optionHeadings(), $this->optionRows()),
            new ProductsExportSheet('Tarifs', $this->priceHeadings(), $this->priceRows()),
            new ProductsExportSheet('Tarifs saisonniers options', $this->seasonHeadings(), $this->seasonRows()),
        ];
    }

    // ───────────── Feuille « Produits » ─────────────

    private function productHeadings(): array
    {
        return [
            'ID', 'Prestation', 'Catégorie', 'Actif', 'Fournisseurs', 'Description',
            'Ville du guide', 'Journée entière (guide)', 'Début guide', 'Fin guide', 'Guide requis',
            'Titre culture', 'Texte culture',
            'Pax max', 'Âge max enfant', 'Âge max adolescent', 'Âge min enfant',
            'Sélecteur enfant', 'Sélecteur adolescent',
            'Premium', 'Familial', 'Disponibilité limitée (loterie)', 'Sur devis',
            'Jours d\'ouverture', 'Dates exclues',
            'Délai avant ouverture (jours)', 'Délai limite de demande (jours)',
            'Libellé date', 'Libellé quantité', 'Libellé multiplicateur',
            'Groupes de clients autorisés',
            'Annulation', 'Détails des frais d\'annulation',
            'Champs personnalisés', 'Documents importants', 'Photos',
            'Note interne', 'Créé le', 'Modifié le',
        ];
    }

    private function productRows(): array
    {
        return $this->products->map(fn (Product $p): array => [
            $p->id,
            $p->name,
            $p->category?->name,
            $this->yesNo($p->is_public),
            $p->productSuppliers->map(fn ($ps) => $ps->supplier?->name)->filter()->implode(', '),
            $p->description,
            $p->guide_city,
            $this->yesNo($p->guide_all_day ?? null),
            $this->time($p->guide_start_time ?? null),
            $this->time($p->guide_end_time ?? null),
            $this->yesNo($p->requires_guide),
            $p->culture_title,
            $p->culture_text,
            $p->max_pax,
            $p->child_age_limit,
            $p->teen_age_limit,
            $p->min_child_age,
            $this->yesNo($p->enable_child_selector),
            $this->yesNo($p->enable_teen_selector),
            $this->yesNo($p->is_premium),
            $this->yesNo($p->is_family_friendly),
            $this->yesNo($p->is_lottery),
            $this->yesNo($p->is_on_demand),
            $this->days($p->available_days),
            collect($p->blackout_dates ?? [])->map(fn ($d) => is_array($d) ? ($d['date'] ?? null) : $d)->filter()->implode(', '),
            $p->days_before_opening,
            $p->days_before_request_deadline,
            $p->date_label,
            $p->quantity_label,
            $p->multiplier_label,
            $p->clientGroups->pluck('name')->implode(', ') ?: 'Toutes les agences',
            $p->cancellation_type === 'specific' ? 'Barème spécifique' : 'Barème général',
            $p->cancellation_specifics,
            $this->customFields($p->custom_field_definitions),
            collect($p->important_documents ?? [])->map(fn ($d) => $d['label'] ?? ($d['file'] ?? null))->filter()->implode(', '),
            collect($this->images($p))->implode("\n"),
            $p->internal_note,
            $p->created_at?->format('d/m/Y H:i'),
            $p->updated_at?->format('d/m/Y H:i'),
        ])->all();
    }

    // ───────────── Feuille « Options » ─────────────

    private function optionHeadings(): array
    {
        return [
            'ID produit', 'Prestation', 'ID option', 'Groupe', 'Option', 'Code', 'Ville du guide',
            'Début guide', 'Fin guide', 'Jours propres', 'Obligatoire', 'Facturation',
            'Supplément général (¥)', 'Supplément adulte (¥)', 'Supplément enfant (¥)', 'Supplément ado (¥)',
        ];
    }

    private function optionRows(): array
    {
        return $this->products->flatMap(fn (Product $p) => $p->productOptions->map(fn (ProductOption $o): array => [
            $p->id,
            $p->name,
            $o->id,
            $o->group_name,
            $o->name,
            $o->code,
            $o->guide_city,
            $this->time($o->guide_start_time),
            $this->time($o->guide_end_time),
            $this->days($o->available_days),
            $this->yesNo($o->is_required),
            self::BILLING[$o->billing_type] ?? $o->billing_type,
            $o->price_modifier,
            $o->adult_price_modifier,
            $o->child_price_modifier,
            $o->teen_price_modifier,
        ]))->all();
    }

    // ───────────── Feuille « Tarifs » ─────────────

    private function priceHeadings(): array
    {
        return [
            'ID produit', 'Prestation', 'Saison', 'Période(s)', 'Grille liée à l\'option',
            'Type de tarif', 'Pax min', 'Pax max', 'Âge min', 'Âge max',
            'Prix de vente (¥)', 'Prix d\'achat (¥)', 'Supplément pax sup. (¥, ancien champ)',
        ];
    }

    private function priceRows(): array
    {
        $rows = [];

        foreach ($this->products as $p) {
            $optionNames = $p->productOptions->pluck('name', 'id');

            foreach ($p->productPeriods->concat($p->variantPeriods) as $period) {
                /** @var ProductPeriod $period */
                $ranges = collect($period->dateRanges())
                    ->map(fn (array $r): string => $r['start_date'].' → '.$r['end_date'])
                    ->implode(' ; ') ?: 'Toute l\'année';

                if ($period->productPrices->isEmpty()) {
                    $rows[] = [$p->id, $p->name, $period->name, $ranges, $optionNames[$period->variant_option_id] ?? null, null, null, null, null, null, null, null, null];

                    continue;
                }

                foreach ($period->productPrices as $price) {
                    /** @var ProductPrice $price */
                    $rows[] = [
                        $p->id,
                        $p->name,
                        $period->name,
                        $ranges,
                        $optionNames[$period->variant_option_id] ?? null,
                        self::PRICING[$price->pricing_mode] ?? 'Par pax',
                        $price->min_pax,
                        $price->max_pax,
                        $price->min_age,
                        $price->max_age,
                        $price->price,
                        $price->purchase_price,
                        $price->extra_pax_price,
                    ];
                }
            }
        }

        return $rows;
    }

    // ───────────── Feuille « Tarifs saisonniers options » ─────────────

    private function seasonHeadings(): array
    {
        return [
            'ID produit', 'Prestation', 'Option', 'Saison', 'Tranche de tarif',
            'Supplément général (¥)', 'Supplément adulte (¥)', 'Supplément enfant (¥)', 'Supplément ado (¥)', 'Écart prix d\'achat (¥)',
        ];
    }

    private function seasonRows(): array
    {
        $rows = [];

        foreach ($this->products as $p) {
            $prices = $p->productPeriods->concat($p->variantPeriods)->flatMap->productPrices->keyBy('id');

            foreach ($p->productOptions as $option) {
                foreach ($option->seasonalPrices as $s) {
                    $tier = $s->product_price_id ? $prices->get($s->product_price_id) : null;

                    $rows[] = [
                        $p->id,
                        $p->name,
                        $option->name,
                        $s->productPeriod?->name,
                        $tier ? trim(($tier->min_pax ?? '').'–'.($tier->max_pax ?? '').' pax') : 'Toutes les tranches',
                        $s->price_modifier,
                        $s->adult_price_modifier,
                        $s->child_price_modifier,
                        $s->teen_price_modifier,
                        $s->purchase_price_modifier,
                    ];
                }
            }
        }

        return $rows;
    }

    // ───────────── Utilitaires ─────────────

    private function yesNo(mixed $value): ?string
    {
        return $value === null ? null : ($value ? 'Oui' : 'Non');
    }

    private function time(mixed $value): ?string
    {
        return filled($value) ? substr((string) $value, 0, 5) : null;
    }

    private function days(mixed $days): string
    {
        return collect($days ?? [])->map(fn ($d) => self::DAYS[$d] ?? $d)->implode(', ');
    }

    private function customFields(mixed $definitions): string
    {
        return collect($definitions ?? [])->map(function ($f): string {
            $line = ($f['name'] ?? '?').' ['.(self::FIELD_TYPES[$f['type'] ?? ''] ?? ($f['type'] ?? '?')).']';
            if (! empty($f['choices'])) {
                $line .= ' : '.implode(' / ', (array) $f['choices']);
            }
            if (! empty($f['is_required'])) {
                $line .= ' (obligatoire)';
            }
            if (! empty($f['is_per_passenger'])) {
                $line .= ' (par passager)';
            }

            return $line;
        })->implode("\n");
    }

    /** @return array<int, string> */
    private function images(Product $p): array
    {
        $images = $p->images;
        if (is_string($images)) {
            $images = json_decode($images, true);
        }

        return collect(is_array($images) ? $images : [])
            ->filter(fn ($i) => is_string($i) && filled($i))
            ->map(fn (string $i): string => str_starts_with($i, 'http') ? $i : asset('storage/'.ltrim(preg_replace('#^/?storage/#', '', $i), '/')))
            ->values()
            ->all();
    }
}
