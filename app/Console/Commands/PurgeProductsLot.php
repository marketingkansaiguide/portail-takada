<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionSeasonPrice;
use App\Models\ProductPeriod;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurgeProductsLot extends Command
{
    protected $signature = 'products:purge-lot
        {file : Fichier JSON du lot dont les produits (par nom) seront supprimés}
        {--dry-run : Simule la suppression puis annule tout}';
    protected $description = 'Supprime les produits d\'un lot (par nom) après contrôle des dossiers qui les référencent';

    public function handle(): int
    {
        try {
            $lot = json_decode(file_get_contents($this->resolvePath($this->argument('file'))), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->error('Lecture du lot impossible : '.$e->getMessage());

            return Command::FAILURE;
        }

        $names = array_column($lot['products'] ?? [], 'name');
        $products = Product::whereIn('name', $names)->get();
        $productIds = $products->pluck('id')->all();

        if ($productIds === []) {
            $this->warn('Aucun produit du lot n\'existe : rien à supprimer.');

            return Command::SUCCESS;
        }

        $folderItems = DB::table('folder_items')->whereIn('product_id', $productIds)->count();

        if ($folderItems > 0) {
            $this->error("Purge refusée : {$folderItems} dossier(s) référencent ces produits. Rien n'a été supprimé.");

            return Command::FAILURE;
        }

        DB::beginTransaction();

        try {
            $periodIds = ProductPeriod::whereIn('product_id', $productIds)->pluck('id')->all();

            $stats = [
                'Prix des options' => ProductOptionSeasonPrice::whereIn('product_id', $productIds)->delete(),
                'Options' => ProductOption::whereIn('product_id', $productIds)->delete(),
                'Tarifs' => ProductPrice::whereIn('product_period_id', $periodIds)->delete(),
                'Périodes' => ProductPeriod::whereIn('id', $periodIds)->delete(),
                'Fournisseurs liés' => ProductSupplier::whereIn('product_id', $productIds)->delete(),
                'Produits' => Product::whereIn('id', $productIds)->delete(),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Purge annulée : '.$e->getMessage());

            return Command::FAILURE;
        }

        if ($this->option('dry-run')) {
            DB::rollBack();
            $this->warn('Simulation : rien n\'a été supprimé.');
        } else {
            DB::commit();
        }

        $this->table(['Élément', 'Supprimés'], collect($stats)->map(fn (int $count, string $label): array => [$label, $count])->values()->all());

        return Command::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        $fullPath = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) ? $path : base_path($path);

        if (! file_exists($fullPath)) {
            throw new RuntimeException("Fichier introuvable : {$fullPath}");
        }

        return $fullPath;
    }
}
