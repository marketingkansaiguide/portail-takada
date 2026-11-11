<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crée les fournisseurs manquants de l'Annuaire activités (Excel Billetterie 2026)
 * et les rattache aux produits du catalogue. Idempotent, dry-run par défaut, ne supprime rien.
 */
class SyncSuppliersFromExcel extends Command
{
    protected $signature = 'suppliers:sync-excel {--apply : Écrit réellement en base (sinon simulation)}';

    protected $description = "Crée les fournisseurs manquants de l'Excel et les assigne aux produits (dry-run par défaut)";

    /** Fournisseurs à créer s'ils n'existent pas (match par nom normalisé ou alias). */
    private function suppliers(): array
    {
        return [
            ['key' => 'pinpoint', 'name' => 'Pinpoint Traveler', 'email' => 'bookings@pinpointtraveler.com', 'phone' => '070-9127-3993'],
            ['key' => 'sakura', 'name' => 'Sakura Cook', 'contact' => 'Fumiyo Okamoto', 'email' => 'contact@sakuracook.jp', 'phone' => '070-8561-5030'],
            ['key' => 'otafuku', 'name' => 'Otafuku Okosta', 'email' => 'okosta1@otafuku.co.jp', 'phone' => '082-207-1277'],
            ['key' => 'wazuka', 'name' => 'Wazuka-cho Kasseika Center', 'email' => 'wazuka-h@chagenkyo.com', 'phone' => '0774-78-3396'],
            ['key' => 'waraku', 'name' => 'Waraku', 'email' => 'support@waraku.jp', 'phone' => '075-841-2936'],
            ['key' => 'japanism', 'name' => 'Japanism', 'email' => 'h.suzuki@japanism.co.jp, kaz@japanism.co.jp', 'notes' => "Non disponible jusqu'en juin 2027"],
            ['key' => 'tokyoheritage', 'name' => 'Tokyo Heritage', 'contact' => 'Furukawa', 'email' => 'furukawa@tokyo-heritage.jp', 'phone' => '080-5644-2229'],
            ['key' => 'forgenin', 'name' => 'Forge NIN (Crea LLC)', 'email' => 'crea.japan.experience@gmail.com', 'phone' => '080-4970-2037'],
            ['key' => 'asano', 'name' => 'Asano Kajiya', 'email' => 'samuraiknife.japan@gmail.com', 'phone' => '058-374-3818'],
            ['key' => 'naturabase', 'name' => 'Natura Base', 'contact' => 'Grahame', 'email' => 'naturabase.2022@gmail.com', 'phone' => '070-4484-5408'],
            ['key' => 'ougiya', 'name' => 'Ougiya (Naoshima)', 'email' => 'info@ougiya-naoshima.jp', 'phone' => '090-3189-0471'],
            ['key' => 'fujisanbike', 'name' => 'Fujisanbike Kawaguchiko', 'phone' => '090-5789-3776'],
            ['key' => 'shimanami', 'name' => 'Shimanami Japan', 'phone' => '0848-22-3911'],
            ['key' => 'giant', 'name' => 'Giant Store Onomichi', 'email' => 'onomichi@giant-store.jp', 'phone' => '0848-21-0068'],
            ['key' => 'okamoto', 'name' => 'Okamoto Kimono', 'contact' => 'Hiroichi Hayashi', 'email' => 'contact@okamoto-kimono.com', 'phone' => '090-6750-7853'],
            ['key' => 'asile', 'name' => 'Asile Mauve', 'contact' => 'Kondo', 'email' => 'k.t.9215@ever.ocn.ne.jp', 'phone' => '080-5708-9215'],

            // Existants en prod, sous un nom légèrement différent (jamais recréés) :
            ['key' => 'camelliagarden', 'name' => 'Camellia Garden', 'aliases' => ['Camelia Garden']],
            ['key' => 'amikyoto', 'name' => 'Ami Kyoto'],
            ['key' => 'koto', 'name' => 'Koto'],
            ['key' => 'kenrokuen', 'name' => 'Kenrokuen'],
            ['key' => 'toyooka', 'name' => 'Toyooka Tourism Innovation'],
        ];
    }

    /** Produit (nom exact du catalogue) => clés de fournisseurs à ajouter. */
    private function mapping(): array
    {
        return [
            'Cours de cuisine okonomiyaki – Hiroshima' => ['pinpoint', 'otafuku'],
            'Cours de cuisine – Osaka' => ['sakura'],
            'Cérémonie du thé – Kyoto' => ['camelliagarden', 'amikyoto', 'koto'],
            'Cérémonie du thé – Kanazawa' => ['kenrokuen'],
            'Dîner avec une maiko ou une geisha – Kanazawa' => ['kenrokuen'],
            'Journée thème matcha – Wazuka' => ['wazuka'],
            'Visite guidée à vélo – Toyooka' => ['toyooka'],
        ];
    }

    private function norm(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($s)));
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'MODE ÉCRITURE' : 'SIMULATION (dry-run) : ajoutez --apply pour écrire');

        $existing = Supplier::all();
        $byNorm = [];
        foreach ($existing as $s) {
            $byNorm[$this->norm($s->name)][] = $s;
        }

        $resolved = [];
        $toCreate = [];
        $hasEmail = DB::getSchemaBuilder()->hasColumn('suppliers', 'email');

        DB::beginTransaction();
        try {
            foreach ($this->suppliers() as $d) {
                $names = array_merge([$d['name']], $d['aliases'] ?? []);
                $found = null;
                foreach ($names as $n) {
                    if (!empty($byNorm[$this->norm($n)])) {
                        $found = $byNorm[$this->norm($n)][0];
                        break;
                    }
                }
                if ($found) {
                    $resolved[$d['key']] = $found;
                    $this->line("= existe : {$d['name']} (#{$found->id} {$found->name})");
                    continue;
                }
                $data = [
                    'name' => $d['name'],
                    'contact_name' => $d['contact'] ?? null,
                    'phone' => $d['phone'] ?? null,
                    'notes' => $d['notes'] ?? null,
                ];
                if ($hasEmail) {
                    $data['email'] = $d['email'] ?? null;
                }
                $resolved[$d['key']] = Supplier::create($data);
                $toCreate[] = $d['name'];
                $this->line("+ créé   : {$d['name']}");
            }

            $links = 0;
            $missing = [];
            foreach ($this->mapping() as $productName => $keys) {
                $products = Product::where('name', $productName)->get();
                if ($products->isEmpty()) {
                    $missing[] = $productName;
                    $this->warn("! produit introuvable : $productName");
                    continue;
                }
                foreach ($products as $p) {
                    foreach ($keys as $k) {
                        $sup = $resolved[$k];
                        $exists = DB::table('product_suppliers')
                            ->where('product_id', $p->id)->where('supplier_id', $sup->id)->exists();
                        if ($exists) {
                            $this->line("= lien existe : #{$p->id} {$p->name} ↔ {$sup->name}");
                            continue;
                        }
                        DB::table('product_suppliers')->insert([
                            'product_id' => $p->id,
                            'supplier_id' => $sup->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $links++;
                        $this->line("+ lien   : #{$p->id} {$p->name} ↔ {$sup->name}");
                    }
                }
            }

            $this->info(count($toCreate) . " fournisseur(s) à créer, $links lien(s) à ajouter, " . count($missing) . ' produit(s) introuvable(s).');

            if ($apply) {
                DB::commit();
                $this->info('Écrit en base.');
            } else {
                DB::rollBack();
                $this->info('Rien écrit (simulation).');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return self::SUCCESS;
    }
}
