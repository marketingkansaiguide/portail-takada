<?php

namespace App\Filament\Resources\Folders;

use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * En-tête collant de la page d'édition d'un dossier : référence, client, dates,
 * pax, totaux (Vente / Achat / Marge) et liens vers les sections.
 *
 * Les totaux reprennent exactement la logique des champs « Statut & Facturation »
 * (prix de vente = prestations + internes + frais de dossier ; achat = somme des
 * coûts d'achat ; marge = vente - achat).
 */
class FolderStickyHeader
{
    public static function component(): Placeholder
    {
        return Placeholder::make('tk_sticky_header')
            ->hiddenLabel()
            ->columnSpanFull()
            ->dehydrated(false)
            ->content(function (Get $get, $record) {
                if (! $record) {
                    return '';
                }

                $items = $get('folderItems') ?? [];
                $internalItems = $get('internalItems') ?? [];
                $fee = (float) ($get('folder_fee') ?? 0);
                $totals = self::totals($items, $internalItems, $fee);

                $view = view('filament.folders.sticky-header', [
                    'reference' => $record->reference ?: ('#' . $record->id),
                    'name' => $record->folder_name ?: $record->lead_traveler_name,
                    'dates' => self::formatDate($get('start_date'), 'd/m') . ' → ' . self::formatDate($get('end_date'), 'd/m/Y'),
                    'pax' => (int) ($get('pax_adults') ?? 0) + (int) ($get('pax_children') ?? 0),
                    'sale' => $totals['sale'],
                    'cost' => $totals['cost'],
                    'margin' => $totals['margin'],
                    'urgentNote' => $record->ticketing_urgent ? ($record->ticketing_urgent_note ?: __('Consigne urgente (voir les consignes du dossier)')) : null,
                    'countItems' => count($items),
                    'countInternal' => count($internalItems),
                ]);

                return new HtmlString($view->render());
            });
    }

    /**
     * @return array{sale: float, cost: float, margin: float}
     */
    public static function totals(array $items, array $internalItems, float $fee): array
    {
        $sale = 0.0;
        $cost = 0.0;

        foreach ($items as $item) {
            $sale += (float) ($item['total_price'] ?? 0);
            $cost += (float) ($item['purchase_total_price'] ?? 0);
        }
        foreach ($internalItems as $item) {
            $sale += (float) ($item['total_price'] ?? 0);
            $cost += (float) ($item['purchase_total_price'] ?? 0);
        }

        $sale += $fee;

        return ['sale' => $sale, 'cost' => $cost, 'margin' => $sale - $cost];
    }

    private static function formatDate($value, string $format): string
    {
        if (blank($value)) {
            return '—';
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return '—';
        }
    }
}
