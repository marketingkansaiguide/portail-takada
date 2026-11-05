<?php

namespace App\Filament\Resources\Folders;

use App\Models\FolderItem;
use App\Models\ItemStatus;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Vue « jour par jour » des prestations d'un dossier (back-office),
 * calquée sur le tableau « Prestations et transport » des fichiers Excel.
 *
 * Elle lit l'état courant du formulaire (prestations + prestations internes)
 * et affiche une ligne par prestation, regroupée par jour du séjour.
 * Un clic sur une ligne ouvre la fiche complète dans le repeater existant.
 */
class FolderDayByDay
{
    public static function component(): Placeholder
    {
        return Placeholder::make('day_by_day_view')
            ->hiddenLabel()
            ->columnSpanFull()
            ->dehydrated(false)
            ->content(fn (Get $get): HtmlString => static::render(
                $get('folderItems') ?? [],
                $get('internalItems') ?? [],
                $get('start_date'),
                $get('end_date'),
            ));
    }

    public static function render(array $items, array $internalItems, $startDate, $endDate): HtmlString
    {
        $items = is_array($items) ? $items : [];
        $internalItems = is_array($internalItems) ? $internalItems : [];

        $productIds = collect($items)->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $optionIds = collect($items)->flatMap(function ($item) {
            $ids = collect($item['selected_options'] ?? [])->pluck('product_option_id');
            if (! empty($item['product_option_id'])) {
                $ids->push($item['product_option_id']);
            }

            return $ids;
        })->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $statusIds = collect($items)->merge($internalItems)->pluck('item_status_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $products = $productIds->isEmpty() ? collect() : Product::query()->whereIn('id', $productIds)->pluck('name', 'id');
        $options = $optionIds->isEmpty() ? collect() : ProductOption::query()->whereIn('id', $optionIds)->pluck('name', 'id');
        $statuses = $statusIds->isEmpty() ? collect() : ItemStatus::query()->whereIn('id', $statusIds)->get()->keyBy('id');
        $productSuppliers = $productIds->isEmpty() ? collect() : ProductSupplier::query()->whereIn('product_id', $productIds)->get(['product_id', 'supplier_id'])->groupBy('product_id');

        $rows = [];

        foreach ($items as $key => $state) {
            if (! is_array($state)) {
                continue;
            }

            $productId = ! empty($state['product_id']) ? (int) $state['product_id'] : null;
            $storedSupplierId = ! empty($state['supplier_id']) ? (int) $state['supplier_id'] : null;

            // Même règle que l'en-tête replié : fournisseur actuel du produit.
            $supplierIds = $productId ? collect($productSuppliers->get($productId, []))->pluck('supplier_id')->map(fn ($id) => (int) $id) : collect();
            $currentSupplierId = null;
            $mustSelectSupplier = false;
            if ($productId) {
                if ($storedSupplierId && $supplierIds->contains($storedSupplierId)) {
                    $currentSupplierId = $storedSupplierId;
                } elseif ($supplierIds->count() === 1) {
                    $currentSupplierId = $supplierIds->first();
                } elseif ($supplierIds->count() > 1) {
                    $mustSelectSupplier = true;
                }
            } else {
                $currentSupplierId = $storedSupplierId;
            }

            $optionNames = collect($state['selected_options'] ?? [])
                ->pluck('product_option_id')
                ->filter()
                ->map(fn ($id) => $options->get((int) $id))
                ->filter()
                ->values();
            if ($optionNames->isEmpty() && ! empty($state['product_option_id'])) {
                $name = $options->get((int) $state['product_option_id']);
                if ($name) {
                    $optionNames->push($name);
                }
            }

            $rows[] = [
                'key' => (string) $key,
                'repeater' => 'folderItems',
                'internal' => false,
                'date' => static::parseDate($state['service_date'] ?? null),
                'name' => $productId ? ($products->get($productId) ?? __('Produit inconnu')) : __('Nouvelle ligne de prestation'),
                'options' => $optionNames->isNotEmpty() ? $optionNames->implode(', ') : ($productId ? __('Sans option') : ''),
                'quantity' => $state['quantity'] ?? 1,
                'supplier_id' => $currentSupplierId,
                'must_select_supplier' => $mustSelectSupplier,
                'invoice_received' => ! empty($state['invoice_received_at']),
                'status' => ! empty($state['item_status_id']) ? $statuses->get((int) $state['item_status_id']) : null,
                'price' => (float) ($state['total_price'] ?? 0),
            ];
        }

        foreach ($internalItems as $key => $state) {
            if (! is_array($state)) {
                continue;
            }

            $rows[] = [
                'key' => (string) $key,
                'repeater' => 'internalItems',
                'internal' => true,
                'date' => static::parseDate($state['service_date'] ?? null),
                'name' => filled($state['title'] ?? null) ? $state['title'] : __('Prestation sans titre'),
                'options' => '',
                'quantity' => $state['quantity'] ?? 1,
                'supplier_id' => null,
                'must_select_supplier' => false,
                'invoice_received' => true,
                'status' => ! empty($state['item_status_id']) ? $statuses->get((int) $state['item_status_id']) : null,
                'price' => (float) ($state['purchase_total_price'] ?? 0),
            ];
        }

        $supplierModels = collect($rows)->pluck('supplier_id')->filter()->unique()->values();
        $suppliers = $supplierModels->isEmpty() ? collect() : Supplier::query()->whereIn('id', $supplierModels)->get(['id', 'name', 'requires_invoice'])->keyBy('id');

        foreach ($rows as &$row) {
            $supplier = $row['supplier_id'] ? $suppliers->get($row['supplier_id']) : null;
            $row['supplier'] = $supplier?->name;
            $row['awaiting_invoice'] = ! $row['internal'] && $supplier && $supplier->requires_invoice && ! $row['invoice_received'];
            $row['status_name'] = $row['status']?->name ?? __('Aucun statut');
            $row['status_tone'] = match ($row['status']?->color) {
                'success' => 'success',
                'warning' => 'warning',
                'danger' => 'danger',
                'info' => 'info',
                default => 'gray',
            };
            $statusName = Str::lower(Str::ascii((string) $row['status']?->name));
            $row['cancelled'] = str_contains($statusName, 'annul') || str_contains($statusName, 'pas de disponibilit');
        }
        unset($row);

        // Jours du séjour : du début à la fin du dossier, élargis aux dates des prestations.
        $start = static::parseDate($startDate);
        $end = static::parseDate($endDate);
        $dated = collect($rows)->pluck('date')->filter();
        if ($dated->isNotEmpty()) {
            $minItem = $dated->min();
            $maxItem = $dated->max();
            $start = $start && $start->lte($minItem) ? $start : $minItem;
            $end = $end && $end->gte($maxItem) ? $end : $maxItem;
        }
        if ($start && $end && $end->lt($start)) {
            $end = $start->copy();
        }

        $byDate = collect($rows)->filter(fn ($row) => $row['date'])->groupBy(fn ($row) => $row['date']->toDateString());
        $undated = collect($rows)->filter(fn ($row) => ! $row['date'])->values();

        $days = [];
        if ($start && $end && $start->diffInDays($end) <= 120) {
            $number = 1;
            foreach (CarbonPeriod::create($start, $end) as $date) {
                $days[] = [
                    'number' => $number++,
                    'date' => $date->copy(),
                    'is_start' => $startDate && $date->isSameDay(static::parseDate($startDate)),
                    'is_end' => $endDate && $date->isSameDay(static::parseDate($endDate)),
                    'rows' => $byDate->get($date->toDateString(), collect())->values()->all(),
                ];
            }
        }

        $counts = collect($rows)->groupBy('status_tone')->map->count();
        $billable = collect($rows)->where('internal', false)->where('cancelled', false)->sum('price');

        return new HtmlString(view('filament.folders.day-by-day', [
            'days' => $days,
            'undated' => $undated->all(),
            'total' => count($rows),
            'counts' => $counts,
            'cancelledCount' => collect($rows)->where('cancelled', true)->count(),
            'billable' => $billable,
        ])->render());
    }

    protected static function parseDate($value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
