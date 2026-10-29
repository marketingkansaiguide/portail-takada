<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionSeasonPrice;
use App\Models\ProductPeriod;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportProducts extends Command
{
    protected $signature = 'import:products
        {file : Fichier JSON du lot}
        {--seasons=storage/app/imports/saisons-2027.json : Fichier des saisons}
        {--dry-run : Simule l\'import puis annule tout}';
    protected $description = 'Importe un lot de produits (visites, accueils, transferts) depuis un JSON préparé';

    private const GUIDING_CATEGORY_ID = 4;

    private const SEASON_NAMES = [
        'basse' => 'Basse saison 2027',
        'haute' => 'Haute saison 2027',
        'tres_haute' => 'Très haute saison 2027',
    ];

    public function handle(): int
    {
        try {
            $lot = $this->readJson($this->argument('file'));
            $seasons = $this->readJson($this->option('seasons'));
            $definitions = $this->periodDefinitions($seasons);
            $this->validate($lot, $definitions);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        DB::beginTransaction();

        try {
            $stats = $this->import($lot, $definitions);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import annulé : '.$e->getMessage());

            return Command::FAILURE;
        }

        if ($this->option('dry-run')) {
            DB::rollBack();
            $this->warn('Simulation : rien n\'a été enregistré.');
        } else {
            DB::commit();
        }

        $this->table(['Élément', 'Créés'], collect($stats)->map(fn (int $count, string $label): array => [$label, $count])->values()->all());

        return Command::SUCCESS;
    }

    private function readJson(string $path): array
    {
        $fullPath = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) ? $path : base_path($path);

        if (! file_exists($fullPath)) {
            throw new RuntimeException("Fichier introuvable : {$fullPath}");
        }

        return json_decode(file_get_contents($fullPath), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Périodes utilisables par le lot : les trois saisons 2027 et une période annuelle pour les transferts.
     */
    private function periodDefinitions(array $seasons): array
    {
        $definitions = [];

        foreach ($seasons['periods'] ?? [] as $period) {
            $key = array_search($period['name'], self::SEASON_NAMES, true);

            if ($key === false) {
                throw new RuntimeException("Saison inconnue dans le fichier : {$period['name']}");
            }

            $definitions[$key] = ['name' => $period['name'], 'date_ranges' => $this->annualRanges($period['date_ranges'])];
        }

        $definitions['annee'] = [
            'name' => 'Toute l\'année 2027',
            'date_ranges' => [['01-01', '12-31']],
        ];

        return $definitions;
    }

    /**
     * Les périodes sont annuelles en base (MM-JJ) : l'année du fichier est retirée.
     */
    private function annualRanges(array $ranges): array
    {
        return array_map(
            fn (array $range): array => [substr($range[0], 5), substr($range[1], 5)],
            $ranges,
        );
    }

    /**
     * Contrôle tout le lot avant d'écrire quoi que ce soit.
     */
    private function validate(array $lot, array $definitions): void
    {
        $errors = [];

        foreach ($lot['products'] ?? [] as $product) {
            $label = $product['key'] ?? '?';
            $guiding = ($product['cost_mode'] ?? null) === 'guiding_option';

            if (empty($product['name']) || empty($product['prices'])) {
                $errors[] = "{$label} : nom ou tarifs manquants";
            }

            if ($guiding && ($product['category_id'] ?? null) !== self::GUIDING_CATEGORY_ID) {
                $errors[] = "{$label} : un produit guidé doit être en catégorie ".self::GUIDING_CATEGORY_ID;
            }

            if (Product::where('name', $product['name'] ?? '')->exists()) {
                $errors[] = "{$label} : un produit « {$product['name']} » existe déjà";
            }

            foreach ($product['prices'] ?? [] as $row) {
                if (! isset($definitions[$row['period']])) {
                    $errors[] = "{$label} : période inconnue {$row['period']}";
                }

                if (($row['pricing_mode'] ?? null) === 'global') {
                    $cost = $guiding ? ($row['guide_cost'] ?? null) : ($row['purchase_price'] ?? null);

                    if ($row['price'] === null || $cost === null) {
                        $errors[] = "{$label} : tarif ou PA manquant ({$row['period']}, {$row['min_pax']} pax)";
                    } elseif ($cost > $row['price']) {
                        $errors[] = "{$label} : PA supérieur au PV ({$row['period']}, {$row['min_pax']} pax)";
                    }
                }

                if ($guiding && ($row['pricing_mode'] ?? null) === 'extra_pax' && ($row['guide_cost_extra_pax'] ?? null) === null) {
                    $errors[] = "{$label} : PA par pax supplémentaire manquant ({$row['period']})";
                }
            }
            $priceKeys = array_map(fn (array $row): string => $row['period'].'|'.$row['min_pax'], $product['prices'] ?? []);

            foreach ($product['options'] ?? [] as $option) {
                foreach ($option['rows'] ?? [] as $row) {
                    if (! in_array($row['period'].'|'.$row['min_pax'], $priceKeys, true)) {
                        $errors[] = "{$label} : option « {$option['name']} » sans tarif ({$row['period']}, {$row['min_pax']} pax)";
                    }
                }
            }
        }

        if ($errors !== []) {
            throw new RuntimeException("Lot refusé, {$errors[0]}".(count($errors) > 1 ? ' (et '.(count($errors) - 1).' autre(s))' : ''));
        }
    }

    private function import(array $lot, array $definitions): array
    {
        $stats = [
            'Produits' => 0,
            'Périodes' => 0,
            'Tarifs' => 0,
            'Options' => 0,
            'Prix des options' => 0,
        ];

        foreach ($lot['products'] as $data) {
            $supplier = Supplier::firstOrCreate(['name' => $data['supplier']]);
            $categoryId = $data['category_id'] ?? Category::firstOrCreate(['name' => $data['category']])->id;
            $guiding = ($data['cost_mode'] ?? null) === 'guiding_option';

            $product = Product::create([
                'name' => $data['name'],
                'category_id' => $categoryId,
                'supplier_id' => $supplier->id,
                'is_public' => true,
                'is_on_demand' => $data['is_on_demand'] ?? false,
                'is_lottery' => $data['is_lottery'] ?? false,
                'guide_city' => $data['guide_city'] ?? null,
                'guide_start_time' => $data['guide_start_time'] ?? null,
                'guide_end_time' => $data['guide_end_time'] ?? null,
                'guide_all_day' => $data['guide_all_day'] ?? false,
            ]);
            ProductSupplier::firstOrCreate(['product_id' => $product->id, 'supplier_id' => $supplier->id]);
            $stats['Produits']++;

            $periods = [];
            $priceIds = [];
            foreach (array_unique(array_column($data['prices'], 'period')) as $key) {
                $definition = $definitions[$key];
                $starts = array_column($definition['date_ranges'], 0);
                $ends = array_column($definition['date_ranges'], 1);

                $periods[$key] = ProductPeriod::create([
                    'product_id' => $product->id,
                    'name' => $definition['name'],
                    'start_date' => min($starts),
                    'end_date' => max($ends),
                    'date_ranges' => array_map(
                        fn (array $range): array => ['start_date' => $range[0], 'end_date' => $range[1]],
                        $definition['date_ranges'],
                    ),
                ]);
                $stats['Périodes']++;
            }

            foreach ($data['prices'] as $row) {
                $price = ProductPrice::create([
                    'product_period_id' => $periods[$row['period']]->id,
                    'min_pax' => $row['min_pax'],
                    'max_pax' => $row['max_pax'],
                    'price' => $row['price'] ?? 0,
                    'purchase_price' => $guiding ? $this->guideCost($row) : ($row['purchase_price'] ?? null),
                    'pricing_mode' => $row['pricing_mode'],
                    'extra_pax_price' => $row['extra_pax_price'] ?? null,
                ]);
                $stats['Tarifs']++;
                $priceIds[$row['period'].'|'.$row['min_pax']] = $price->id;
            }

            foreach ($data['options'] ?? [] as $option) {
                $productOption = ProductOption::create([
                    'product_id' => $product->id,
                    'name' => $option['name'],
                    'group_name' => $option['group_name'],
                    'code' => $option['code'],
                    'billing_type' => $option['billing_type'] ?? 'per_booking',
                    'is_required' => $option['is_required'] ?? false,
                    'guide_city' => $option['guide_city'] ?? null,
                    'guide_start_time' => $option['guide_start_time'] ?? null,
                    'guide_end_time' => $option['guide_end_time'] ?? null,
                    'price_modifier' => 0,
                ]);
                $stats['Options']++;

                foreach ($option['rows'] as $row) {
                    ProductOptionSeasonPrice::create([
                        'product_id' => $product->id,
                        'product_option_id' => $productOption->id,
                        'product_period_id' => $periods[$row['period']]->id,
                        'product_price_id' => $priceIds[$row['period'].'|'.$row['min_pax']],
                        'price_modifier' => $row['price_modifier'],
                        'purchase_price_modifier' => $row['purchase_price_modifier'],
                    ]);
                    $stats['Prix des options']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Pour un produit guidé, le PA du guide est porté directement par la tranche de tarif.
     */
    private function guideCost(array $row): ?int
    {
        $cost = $row['pricing_mode'] === 'extra_pax'
            ? ($row['guide_cost_extra_pax'] ?? null)
            : ($row['guide_cost'] ?? null);

        return $cost === null ? null : (int) $cost;
    }
}
