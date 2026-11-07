<?php

namespace Tests\Support;

use App\Filament\Resources\Folders\FolderResource;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue de référence (copie de la production du 2026-11-07, sans données
 * personnelles) et calcul des prix par le moteur unique
 * FolderResource::updateItemPrices, utilisé par l'admin, le panel agence et
 * l'ajout depuis la fiche produit.
 */
final class Catalogue
{
    public const FIXTURE = __DIR__ . '/../Fixtures/catalogue.json';
    public const SNAPSHOT = __DIR__ . '/../Fixtures/price-snapshot.json';

    public static function load(): void
    {
        $data = json_decode(file_get_contents(self::FIXTURE), true, flags: JSON_THROW_ON_ERROR);

        // Les coordonnées des fournisseurs ne sont pas exportées : fournisseurs anonymes.
        $supplierIds = collect($data['products'])->pluck('supplier_id')->filter()->unique();
        foreach ($supplierIds as $id) {
            DB::table('suppliers')->insert(['id' => $id, 'name' => "Fournisseur {$id}"]);
        }

        foreach ($data as $table => $rows) {
            DB::table($table)->delete();
            foreach (array_chunk($rows, 50) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }

    /** @return array<string, mixed> */
    public static function price(array $state): array
    {
        $get = function (string $key) use (&$state) {
            return $state[$key] ?? null;
        };
        $set = function (string $key, $value) use (&$state): void {
            $state[$key] = $value;
        };

        FolderResource::updateItemPrices($set, $get);

        return [
            'quantity' => isset($state['quantity']) ? (int) $state['quantity'] : null,
            'unit_price' => isset($state['unit_price']) ? round((float) $state['unit_price'], 4) : null,
            'total_price' => isset($state['total_price']) ? round((float) $state['total_price'], 4) : null,
            'purchase_unit_price' => isset($state['purchase_unit_price']) ? round((float) $state['purchase_unit_price'], 4) : null,
            'purchase_total_price' => isset($state['purchase_total_price']) ? round((float) $state['purchase_total_price'], 4) : null,
        ];
    }

    /**
     * Scénarios déterministes : pour chaque produit, plusieurs dates (toutes
     * saisons), plusieurs tailles de groupe, sans option puis avec chaque option,
     * répartition par âges, multiplicateur et trajets de transport.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function scenarios(): array
    {
        $dates = ['2027-01-15', '2027-04-05', '2027-07-20', '2027-10-10', '2027-12-28'];
        $quantities = [1, 2, 4, 9];
        $scenarios = [];

        foreach (Product::with('productOptions')->orderBy('id')->get() as $product) {
            $optionSets = [[]];
            foreach ($product->productOptions->sortBy('id') as $option) {
                $optionSets[] = [['product_option_id' => $option->id, 'quantity' => 2]];
            }

            foreach ($dates as $date) {
                foreach ($quantities as $quantity) {
                    foreach ($optionSets as $i => $options) {
                        $custom = [];
                        if ($product->usesMultiplier()) {
                            $custom['_multiplier'] = 3;
                        }
                        if ($product->product_type === 'transport') {
                            $custom['transport_routes'] = [['purchase_price' => 5400], ['purchase_price' => 1200]];
                        }

                        $state = [
                            'product_id' => $product->id,
                            'service_date' => $date,
                            'quantity' => $quantity,
                            'selected_options' => $options,
                            'custom_values' => $custom,
                        ];

                        $scenarios["p{$product->id}|{$date}|q{$quantity}|o{$i}"] = $state;

                        if ($product->usesAgeSelector() && $i === 0 && $quantity >= 4) {
                            $scenarios["p{$product->id}|{$date}|q{$quantity}|ages"] = $state + [
                                'age_selector_adults' => $quantity - 2,
                                'age_selector_children' => 1,
                                'age_selector_teenagers' => 1,
                            ];
                        }
                    }
                }
            }
        }

        return $scenarios;
    }
}
