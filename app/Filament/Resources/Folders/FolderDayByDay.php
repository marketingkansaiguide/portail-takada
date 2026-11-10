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
            ->content(fn (Get $get, $record): HtmlString => static::render(
                $get('folderItems') ?? [],
                $get('internalItems') ?? [],
                $get('start_date'),
                $get('end_date'),
                // Dossier interne : une prestation commandée s'annule par son statut, sans suppression.
                canDelete: ! ($record?->isInternal() ?? false),
            ));
    }

    public static function render(array $items, array $internalItems, $startDate, $endDate, bool $canDelete = true): HtmlString
    {
        $items = is_array($items) ? $items : [];
        $internalItems = is_array($internalItems) ? $internalItems : [];

        $productIds = collect($items)->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $optionIds = collect($items)->flatMap(function ($item) {
            $ids = collect($item['selected_options'] ?? [])->pluck('product_option_id');
            if (! empty($item['product_option_id'])) {
                $ids->push($item['product_option_id']);
            }
            foreach ((array) data_get($item, 'custom_values.transport_routes', []) as $route) {
                if (is_array($route) && ! empty($route['option_id'])) {
                    $ids->push($route['option_id']);
                }
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
                'name' => $productId
                    ? (! empty($state['planned_item_id'])
                        ? \App\Support\EntryProfile::itemLabel((string) ($products->get($productId) ?? __('Produit inconnu')), (array) ($state['custom_values'] ?? []), $state['route_from'] ?? null, $state['route_to'] ?? null)
                        : ($products->get($productId) ?? __('Produit inconnu')))
                    : __('Nouvelle ligne de prestation'),
                'options' => $optionNames->isNotEmpty() ? $optionNames->implode(', ') : ($productId ? __('Sans option') : ''),
                'quantity' => $state['quantity'] ?? 1,
                'product_id' => $productId,
                'supplier_id' => $currentSupplierId,
                'must_select_supplier' => $mustSelectSupplier,
                'invoice_received' => ! empty($state['invoice_received_at']),
                'status' => ! empty($state['item_status_id']) ? $statuses->get((int) $state['item_status_id']) : null,
                'price' => (float) ($state['total_price'] ?? 0),
                'routes' => static::routes((array) data_get($state, 'custom_values.transport_routes', []), $options, static::parseDate($state['service_date'] ?? null)),
                'where' => trim(trim((string) ($state['meeting_time'] ?? '')) . ' ' . trim((string) ($state['meeting_point'] ?? ''))),
                'note' => preg_replace('/\s+/', ' ', trim((string) ($state['ticketing_notes'] ?? ''))),
                'purchase' => (float) ($state['purchase_total_price'] ?? 0),
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
                'price' => 0.0,
                'routes' => [],
                'purchase' => (float) ($state['purchase_total_price'] ?? 0),
            ];
        }

        $supplierModels = collect($rows)->pluck('supplier_id')->filter()->unique()->values();
        $suppliers = $supplierModels->isEmpty() ? collect() : Supplier::query()->whereIn('id', $supplierModels)->get(['id', 'name', 'requires_invoice'])->keyBy('id');

        foreach ($rows as &$row) {
            $supplier = $row['supplier_id'] ? $suppliers->get($row['supplier_id']) : null;
            $row['supplier'] = $supplier?->name;
            $contact = $row['internal'] ? ['fax' => false, 'mail' => false] : \App\Models\FolderItem::supplierContactAvailable($row['product_id'], $row['supplier_id']);
            $row['has_fax'] = $contact['fax'];
            $row['has_mail'] = $contact['mail'];
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

        // Jours consécutifs sans prestation (hors arrivée / départ) condensés en une seule ligne.
        $groups = [];
        $freeRun = [];
        $flushFree = function () use (&$groups, &$freeRun) {
            if ($freeRun) {
                $groups[] = ['type' => 'free', 'from' => $freeRun[0], 'to' => end($freeRun), 'count' => count($freeRun)];
                $freeRun = [];
            }
        };
        foreach ($days as $day) {
            if (empty($day['rows']) && ! $day['is_start'] && ! $day['is_end']) {
                $freeRun[] = $day;

                continue;
            }
            $flushFree();
            $groups[] = ['type' => 'day'] + $day;
        }
        $flushFree();
        if ($undated->isNotEmpty()) {
            $groups[] = ['type' => 'day', 'number' => null, 'date' => null, 'is_start' => false, 'is_end' => false, 'rows' => $undated->all()];
        }

        $counts = collect($rows)->groupBy('status_tone')->map->count();
        $billable = collect($rows)->where('internal', false)->where('cancelled', false)->sum('price');
        $purchaseTotal = collect($rows)->where('cancelled', false)->sum('purchase');

        return new HtmlString(view('filament.folders.day-by-day', [
            'dayCount' => count($days),
            'groups' => $groups,
            'total' => count($rows),
            'counts' => $counts,
            'cancelledCount' => collect($rows)->where('cancelled', true)->count(),
            'billable' => $billable,
            'purchaseTotal' => $purchaseTotal,
            'canDelete' => $canDelete,
        ])->render());
    }

    /**
     * Trajets d'un billet train / bus, résumés pour le tableau :
     * « Gare A → Gare B », puis date, horaires, n° de train, classe et pax s'ils sont renseignés.
     */
    protected static function routes(array $routes, $options, ?Carbon $serviceDate = null): array
    {
        $lines = [];
        foreach ($routes as $route) {
            if (! is_array($route) || (blank($route['departure_station'] ?? null) && blank($route['arrival_station'] ?? null))) {
                continue;
            }
            $details = [];
            $date = static::parseDate($route['departure_date'] ?? null);
            if ($date && ! ($serviceDate && $date->isSameDay($serviceDate))) {
                $details[] = $date->format('d/m');
            }
            $times = trim(($route['departure_time'] ?? '') . (filled($route['arrival_time'] ?? null) ? '–' . $route['arrival_time'] : ''));
            if ($times !== '') {
                $details[] = $times;
            }
            if (filled($route['train_number'] ?? null)) {
                $details[] = $route['train_number'];
            }
            if (! empty($route['option_id']) && ($class = $options->get((int) $route['option_id']))) {
                $details[] = $class;
            }
            if (filled($route['pax_count'] ?? null)) {
                $details[] = $route['pax_count'] . ' pax';
            }
            $lines[] = [
                'from' => $route['departure_station'] ?? '?',
                'to' => $route['arrival_station'] ?? '?',
                'details' => implode(' · ', $details),
            ];
        }

        return $lines;
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
