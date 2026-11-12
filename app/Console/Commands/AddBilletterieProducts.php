<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crée les produits qui manquent au catalogue pour rattacher les lignes du récap billetterie.
 * Idempotente (un produit dont le nom existe déjà n'est jamais recréé ni modifié) et sans prix.
 * Les produits créés ne sont PAS publics : ils ne s'affichent pas encore aux agences.
 */
class AddBilletterieProducts extends Command
{
    protected $signature = 'catalogue:add-billetterie-products {--dry-run : Affiche ce qui serait créé, sans écrire}';
    protected $description = 'Ajoute au catalogue les produits de la billetterie absents (véhicule privé, location de voiture, parcs, musées…)';

    private const ACTIVITY = 1;
    private const TRANSPORT = 2;

    /** @return array<int, array<string, mixed>> */
    private function definitions(): array
    {
        $quote = fn (array $extra = []) => array_merge(['is_on_demand' => true], $extra);

        return [
            ['name' => 'Véhicule privé', 'category_id' => self::TRANSPORT, 'extra' => $quote([
                'custom_field_definitions' => [
                    ['key' => 'heure', 'name' => 'Horaire souhaité', 'type' => 'text', 'is_required' => true, 'is_per_passenger' => false],
                    ['key' => 'pickup', 'name' => 'Point de prise en charge (pickup)', 'type' => 'text', 'is_required' => true, 'is_per_passenger' => false],
                    ['key' => 'dropoff', 'name' => 'Point de dépose', 'type' => 'text', 'is_required' => true, 'is_per_passenger' => false],
                ],
            ])],
            ['name' => 'Location de voiture', 'category_id' => self::TRANSPORT, 'extra' => $quote([
                'date_label' => 'Date de récupération', 'quantity_label' => 'Nombre de jours',
                'custom_field_definitions' => [
                    ['key' => 'heure', 'name' => 'Horaire de récupération', 'type' => 'text', 'is_required' => true, 'is_per_passenger' => false],
                    ['key' => 'lieu', 'name' => 'Lieu de récupération et de retour', 'type' => 'text', 'is_required' => true, 'is_per_passenger' => false],
                ],
            ])],
            ['name' => 'Universal Studios Japan (USJ)', 'category_id' => self::ACTIVITY, 'supplier' => 'USJ|Universal'],
            ['name' => 'Parc Ghibli', 'category_id' => self::ACTIVITY, 'supplier' => 'Ghibli'],
            ['name' => 'teamLab Planets Tokyo', 'category_id' => self::ACTIVITY, 'supplier' => 'teamLab'],
            ['name' => 'teamLab Borderless', 'category_id' => self::ACTIVITY, 'supplier' => 'teamLab'],
            ['name' => 'Tokyo DisneySea', 'category_id' => self::ACTIVITY, 'supplier' => 'Disney'],
            ['name' => 'Tokyo Disneyland', 'category_id' => self::ACTIVITY, 'supplier' => 'Disney'],
            ['name' => 'Koya Pass', 'category_id' => self::TRANSPORT, 'supplier' => 'Nankai'],
            ['name' => 'Nankai Rapi:t', 'category_id' => self::TRANSPORT, 'supplier' => 'Nankai'],
            ['name' => 'Miyako Odori', 'category_id' => self::ACTIVITY, 'supplier' => 'Miyako'],
            ['name' => 'Musée international du manga de Kyoto', 'category_id' => self::ACTIVITY, 'supplier' => 'Manga Museum'],
            ['name' => 'Musée Gion Kagai', 'category_id' => self::ACTIVITY, 'supplier' => 'Gion Kagai'],
            ['name' => 'Villa impériale de Katsura', 'category_id' => self::ACTIVITY],
            ['name' => 'Chichu Art Museum (Naoshima)', 'category_id' => self::ACTIVITY],
            ['name' => 'Lee Ufan Museum (Naoshima)', 'category_id' => self::ACTIVITY],
            ['name' => 'Musée Ando (Naoshima)', 'category_id' => self::ACTIVITY],
            ['name' => 'Ferry', 'category_id' => self::TRANSPORT, 'extra' => ['is_on_demand' => true]],
            ['name' => 'Location de vélo', 'category_id' => self::TRANSPORT, 'extra' => ['is_on_demand' => true]],
            ['name' => 'Tokyo Skytree', 'category_id' => self::ACTIVITY],
            ['name' => 'Temple des mousses (Saihoji)', 'category_id' => self::ACTIVITY],
            ['name' => 'Edo Wonderland', 'category_id' => self::ACTIVITY],
            ['name' => 'Naoshima New Museum of Art', 'category_id' => self::ACTIVITY],
            ['name' => 'Snoopy Museum Tokyo', 'category_id' => self::ACTIVITY],
            ['name' => 'Aquarium d’Osaka (Kaiyukan)', 'category_id' => self::ACTIVITY],
            ['name' => 'Fuji-Q Highland', 'category_id' => self::ACTIVITY],
            ['name' => 'Legoland Japan', 'category_id' => self::ACTIVITY],
            ['name' => 'Croisière avec dîner – Yakatabune (Tokyo)', 'category_id' => self::ACTIVITY],
            ['name' => 'Teshima Art Museum', 'category_id' => self::ACTIVITY],
            ['name' => 'Benesse House & Valley Gallery', 'category_id' => self::ACTIVITY],
            ['name' => 'Hiroshi Sugimoto Gallery', 'category_id' => self::ACTIVITY],
            ['name' => 'Musée Kusama Yayoi', 'category_id' => self::ACTIVITY],
            ['name' => 'Musée Nintendo', 'category_id' => self::ACTIVITY],
            ['name' => 'SCMAGLEV and Railway Park', 'category_id' => self::ACTIVITY],
            ['name' => 'Mémorial de la Paix (Hiroshima)', 'category_id' => self::ACTIVITY],
            ['name' => 'Musée d’art contemporain de Kanazawa', 'category_id' => self::ACTIVITY],
            ['name' => 'Yokoo House', 'category_id' => self::ACTIVITY],
            ['name' => 'Yusai-Tei Gallery', 'category_id' => self::ACTIVITY],
            ['name' => 'Naruto Park', 'category_id' => self::ACTIVITY],
            ['name' => 'teamLab Biovortex Kyoto', 'category_id' => self::ACTIVITY],
            ['name' => 'Château de Himeji + Kokoen', 'category_id' => self::ACTIVITY],
            ['name' => 'Circuit de Suzuka', 'category_id' => self::ACTIVITY],
            ['name' => 'Atelier de forge – Studio NIN (Yase, Kyoto)', 'category_id' => self::ACTIVITY, 'extra' => [
                'description' => 'Nouvelle activité 2027. Descriptif : https://www.tourakudo.com/studio-nin-yase (activité numéro 1)',
                'images' => ['products/studio-nin.png'],
            ]],
            ['name' => 'Gion Corner', 'category_id' => self::ACTIVITY, 'extra' => ['description' => 'https://www.kyoto-gioncorner.com/global/en.html']],
            ['name' => 'Train Kurobe Torokko', 'category_id' => self::TRANSPORT],
        ];
    }

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $existing = Product::pluck('name')->map(fn ($n) => Str::slug((string) $n))->all();
        $created = $skipped = 0;

        DB::beginTransaction();
        foreach ($this->definitions() as $def) {
            if (in_array(Str::slug($def['name']), $existing, true)) {
                $this->line("= déjà présent : {$def['name']}");
                $skipped++;

                continue;
            }
            $supplier = isset($def['supplier'])
                ? Supplier::query()->where(fn ($q) => collect(explode('|', $def['supplier']))->each(fn ($p) => $q->orWhere('name', 'like', "%{$p}%")))->first()
                : null;

            $attrs = array_merge([
                'name' => $def['name'],
                'category_id' => $def['category_id'],
                'supplier_id' => $supplier?->id,
                'product_type' => 'standard',
                'is_public' => false,
            ], $def['extra'] ?? []);
            Product::create($attrs);
            $this->info("+ à créer : {$def['name']} (fournisseur : ".($supplier?->name ?? 'aucun').')'.(($def['extra']['is_on_demand'] ?? false) ? ' [sur devis]' : ''));
            $created++;
        }
        $dry ? DB::rollBack() : DB::commit();

        $this->newLine();
        $this->info(($dry ? '[ESSAI À BLANC, rien écrit] ' : '')."Créés : {$created} · déjà présents : {$skipped}");

        return self::SUCCESS;
    }
}
