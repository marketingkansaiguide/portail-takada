<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionSeasonPrice;
use App\Models\ProductPeriod;
use App\Models\ProductSupplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergePrivateVariant extends Command
{
    protected $signature = 'products:merge-private-variant
        {base : ID du produit conservé}
        {private : ID du produit privé à fusionner puis supprimer}
        {--group=Type de cérémonie : Nom du groupe d\'options}
        {--default=En groupe : Libellé de l\'option par défaut}
        {--variant=En privé : Libellé de l\'option qui reprend la grille du produit privé}
        {--dry-run : Annule tout à la fin}';

    protected $description = 'Fusionne un produit privé dans son produit de base sous forme d\'option qui remplace la grille tarifaire';

    public function handle(): int
    {
        $base = Product::find($this->argument('base'));
        $private = Product::find($this->argument('private'));

        if (! $base || ! $private || $base->is($private)) {
            $this->error('Produits introuvables.');

            return Command::FAILURE;
        }

        $folderItems = DB::table('folder_items')->where('product_id', $private->id)->count();
        if ($folderItems > 0) {
            $this->error("Fusion refusée : {$folderItems} dossier(s) référencent « {$private->name} ».");

            return Command::FAILURE;
        }

        DB::beginTransaction();

        try {
            $group = $this->option('group');

            ProductOption::firstOrCreate(
                ['product_id' => $base->id, 'name' => $this->option('default'), 'group_name' => $group],
                ['code' => 'GROUP_TOUR', 'billing_type' => 'per_booking', 'price_modifier' => 0, 'is_required' => false, 'sort_order' => 0],
            );
            $variant = ProductOption::firstOrCreate(
                ['product_id' => $base->id, 'name' => $this->option('variant'), 'group_name' => $group],
                ['code' => 'PRIVATE', 'billing_type' => 'per_booking', 'price_modifier' => 0, 'is_required' => false, 'sort_order' => 1],
            );

            $moved = ProductPeriod::where('product_id', $private->id)->update([
                'product_id' => $base->id,
                'variant_option_id' => $variant->id,
            ]);

            ProductOptionSeasonPrice::where('product_id', $private->id)->delete();
            ProductOption::where('product_id', $private->id)->delete();
            ProductSupplier::where('product_id', $private->id)->delete();
            $private->clientGroups()->detach();
            $private->delete();

            $this->info("« {$private->name} » fusionné dans « {$base->name} » : {$moved} période(s) déplacée(s).");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Fusion annulée : '.$e->getMessage());

            return Command::FAILURE;
        }

        if ($this->option('dry-run')) {
            DB::rollBack();
            $this->warn('Simulation : rien n\'a été modifié.');
        } else {
            DB::commit();
        }

        return Command::SUCCESS;
    }
}
