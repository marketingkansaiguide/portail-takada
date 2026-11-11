<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\Folder;
use App\Models\ItemStatus;
use App\Models\Product;
use App\Models\ProductOption;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

/**
 * Reprise de l'onglet « RÉCAP RÉSAS » de l'Excel billetterie : ESSAI À BLANC UNIQUEMENT.
 * Lit l'Excel, regroupe les lignes en dossiers (un par client et par voyage), rapproche agences,
 * produits et statuts, puis écrit des rapports. N'écrit RIEN dans la base.
 * L'import réel n'est volontairement pas implémenté : il attend le feu vert de Maxime.
 */
class ImportBilletterieRecap extends Command
{
    protected $signature = 'billetterie:import-recap
        {file : Fichier Excel « [2026] Billetterie »}
        {--gap=30 : Écart maximal (jours) entre deux prestations d\'un même voyage}
        {--from= : Ne garder que les prestations à partir de cette date (AAAA-MM-JJ)}
        {--report=storage/app/imports/recap-dryrun : Dossier des rapports CSV}';
    protected $description = 'ESSAI À BLANC de la reprise du récap réservations (n\'écrit rien en base)';

    /** Agences internes : graphies « aplaties » (sans espaces ni ponctuation). */
    private const INTERNAL = ['kv', 'takadatravel', 'btoc', 'famtripfit', 'sortietokyopolefit', 'alinecorboz'];

    /** Graphies de l'Excel → agence unique (confirmé par Maxime). */
    private const ALIASES = [
        'amplitude' => 'Amplitudes', 'amplitudes' => 'Amplitudes',
        'exotic' => 'Exotic', 'laroutedujapon' => 'Exotic',
        'cercledesvacances' => 'Cercle des Vacances', 'cercledesvoyages' => 'Cercle des Vacances',
        'atypika' => 'Atypika', 'atypikatours' => 'Atypika',
        'emotika' => 'Emotika', 'emotikavoyages' => 'Emotika',
        'ryokovoyage' => 'Ryoko Voyage', 'ryokovoyages' => 'Ryoko Voyage',
        'kaleidoscope' => 'Kaleidoscope', 'kaleidoscpe' => 'Kaleidoscope',
        '5continents' => '5 Continents',
        'travelexperts' => 'Travel Experts', 'travelexpert' => 'Travel Experts',
        'saveurduvoyage' => 'Saveurs du Voyage', 'saveursduvoyage' => 'Saveurs du Voyage',
        'ericandthetrip' => 'Eric and the Trip', 'ericthetrip' => 'Eric and the Trip',
        'cfavoyage' => 'CFA Voyages', 'cfavoyages' => 'CFA Voyages',
        'tourdecoree' => 'Tour de Corée', 'time2go' => 'Time 2 Go',
        'japanspecialist' => 'Japan Specialist', 'alliberttrekking' => 'Allibert Trekking',
    ];

    /** Couleur de la colonne A → nom du statut de prestation (décidé par Maxime). */
    private const COLOR_STATUS = [
        'B6D7A8' => 'Confirmé',
        'F9CB9C' => 'En attente de validation',
        'A4C2F4' => 'En cours de traitement',
        'F7A3F3' => 'Confirmé',
        'FFE599' => "En attente d'ouverture des réservations / Achat planifié",
        'B4A7D6' => "En attente d'ouverture des réservations / Achat planifié",
        'B7B7B7' => 'Annulé',
    ];

    /** Familles de libellés → produit du catalogue (motif sur le libellé sans accents). */
    private const FAMILIES = [
        'Carte de transport IC' => '/\bic card|suica|pasmo/',
        'Pocket wifi' => '/wifi|pocket/',
        'Traduction de permis' => '/traduction de permis|permis/',
        'Location de voiture' => '/location de voiture|toyota rent/',
        'Véhicule privé' => '/vehicule prive|minivan|sk limo|taxi|chauffeur/',
        'Universal Studios Japan (USJ)' => '/\busj\b|universal/',
        'Parc Ghibli' => '/ghibli/',
        'teamLab Planets Tokyo' => '/team ?lab planet/',
        'teamLab Borderless' => '/teamlab borderless/',
        'Tokyo DisneySea' => '/disney ?sea/',
        'Tokyo Disneyland' => '/disneyland/',
        'Koya Pass' => '/koya ?pass|nankai koya/',
        'Nankai Rapi:t' => '/rapit|rapi t/',
        'Miyako Odori' => '/miyako odori/',
        'Musée international du manga de Kyoto' => '/musee (du )?manga|manga museum/',
        'Musée Gion Kagai' => '/gion kagai/',
        'Villa impériale de Katsura' => '/katsura/',
        'Chichu Art Museum (Naoshima)' => '/chichu/',
        'Lee Ufan Museum (Naoshima)' => '/lee ufan/',
        'Musée Ando (Naoshima)' => '/ando museum|musee ando/',
        'Visite nocturne du cimetière Okuno-in – Koya-san' => '/okuno/',
        'Affûtage et emmanchage de couteau – Osaka' => '/affutage/',
        'Atelier de confection de kimonos avec déjeuner – Yosano (Kyoto)' => '/ateliers? (de )?confection de kimono/',
        'Tour des temples et jardins – Kamakura' => '/kamakura/',
        'Dégustation et découverte du thé japonais en français – Kyoto' => '/degustation.*the.*francais/',
        'Dîner avec une maiko ou une geisha – Kanazawa' => '/(diner|dejeuner).*(geisha|maiko).*kanazawa/',
        'Dîner avec une maiko ou une geisha – Kyoto' => '/diner.*(geisha|maiko)/',
        'Pocket wifi' => '/e-?sim/',
        'Affûtage et emmanchage de couteau – Osaka' => '/affutage/',
        'Atelier de confection de kimonos avec déjeuner – Yosano (Kyoto)' => '/ateliers? (de )?confection de kimono/',
        'Tour des temples et jardins – Kamakura' => '/kamakura/',
        'Dégustation et découverte du thé japonais en français – Kyoto' => '/degustation.*the.*francais/',
        'Dîner avec une maiko ou une geisha – Kanazawa' => '/(diner|dejeuner).*(geisha|maiko).*kanazawa/',
        'Dîner avec une maiko ou une geisha – Kyoto' => '/diner.*(geisha|maiko)/',
        'Pocket wifi' => '/e-?sim/',
        'Ferry' => '/ferry/',
        'Location de vélo' => '/location de velo|e-?bike/',
        'Tokyo Skytree' => '/sky ?tree/',
        'Temple des mousses (Saihoji)' => '/temple des mousses|saihoji/',
        'Edo Wonderland' => '/edo wonderland/',
        'Naoshima New Museum of Art' => '/naoshima new museum/',
        'Snoopy Museum Tokyo' => '/snoopy/',
        'Aquarium d’Osaka (Kaiyukan)' => '/aquarium osaka|kaiyukan/',
        'Fuji-Q Highland' => '/fuji-?q/',
        'Legoland Japan' => '/legoland/',
        'Croisière avec dîner – Yakatabune (Tokyo)' => '/croisiere avec diner|yakatabune/',
        'Teshima Art Museum' => '/teshima/',
        'Benesse House & Valley Gallery' => '/benesse/',
        'Hiroshi Sugimoto Gallery' => '/sugimoto/',
        'Musée Kusama Yayoi' => '/kusama/',
        'Musée Nintendo' => '/nintendo/',
        'SCMAGLEV and Railway Park' => '/scmaglev/',
        'Mémorial de la Paix (Hiroshima)' => '/memorial pour la paix|memorial de la paix/',
        'Musée d’art contemporain de Kanazawa' => '/art contemporain de kanazawa/',
        'Yokoo House' => '/yokoo/',
        'Yusai-Tei Gallery' => '/yusai/',
        'Naruto Park' => '/naruto park/',
        'teamLab Biovortex Kyoto' => '/biovortex/',
        'Château de Himeji + Kokoen' => '/himeji/',
        'Circuit de Suzuka' => '/suzuka/',
        'Gion Corner' => '/gion corner/',
        'Musée interactif ninja & samouraï – Tokyo' => '/musee ninja( \&)? ?tokyo|musee ninja tokyo/',
        'Dégustation et découverte du thé japonais en français – Kyoto' => '/degustation the/',
        'Dîner avec une maiko ou une geisha – Kanazawa' => '/diner maiko kanazawa/',
        'Musée et performance geisha & maiko – Kyoto' => '/spectacle de maiko|spectacle de geisha|spectacle maiko/',
        'Spectacle de rakugo privé en français – Kyoto' => '/rakugo/',
        'Train Kurobe Torokko' => '/torokko/',
        'Cérémonie du thé avec une maiko ou une geisha – Kyoto' => '/c.{1,3}monie du the.*(maiko|geisha)/',
        'Cérémonie du thé – Kanazawa' => '/c.{1,3}monie du the.*(kanazawa|gyokusen)/',
        'Cérémonie du thé – Tokyo' => '/c.{1,3}monie du the.*tokyo/',
        'Cérémonie du thé – Kyoto' => '/c.{1,3}monie du the/',
        'Forge de lame – Otsu (Kyoto)' => '/forge.*(tanaka|otsu)/',
        'Atelier de kintsugi – Tokyo' => '/kintsugi/',
        'Journée thème matcha – Wazuka' => '/wazuka/',
        'Kayak dans la baie – Miyajima' => '/kayak/',
        'Dégustation de saké – Fushimi (Kyoto)' => '/sake/',
        'Danse du sabre (kenbu) – Kyoto' => '/kenbu|samurai experience/',
        'Spectacle de shamisen privé – Kyoto' => '/shamisen/',
        'Sumo – Démonstration et repas (déjeuner ou dîner) – Tokyo' => '/sumo/',
        'Musée interactif ninja & samouraï – Kyoto' => '/musee ninja kyoto|musee ninja&|musee ninja$/',
        'Hakone freepass' => '/hakone/',
        'Ticket de train / bus' => '/shinkansen|train|express|fare ticket|free ?pass|\bbus\b|nohi|kintetsu|tobu|skyliner|romance|nozomi|hikari|thunderbird|\bpass\b|\bjr\b/',
    ];

    /** Billetterie de sites : prix de vente = PA total + frais de résa (règle de Maxime). */
    private const RESA_FEE = 3000;

    private const SITE_TICKET_PRODUCTS = [
        'Universal Studios Japan (USJ)', 'Parc Ghibli', 'teamLab Planets Tokyo', 'teamLab Borderless', 'Tokyo DisneySea', 'Tokyo Disneyland',
        'Musée international du manga de Kyoto', 'Musée Gion Kagai', 'Chichu Art Museum (Naoshima)', 'Lee Ufan Museum (Naoshima)', 'Musée Ando (Naoshima)',
        'Tokyo Skytree', 'Edo Wonderland', 'Naoshima New Museum of Art', 'Snoopy Museum Tokyo', 'Aquarium d’Osaka (Kaiyukan)', 'Fuji-Q Highland', 'Legoland Japan',
        'Croisière avec dîner – Yakatabune (Tokyo)', 'Teshima Art Museum', 'Benesse House & Valley Gallery', 'Hiroshi Sugimoto Gallery', 'Musée Kusama Yayoi', 'Musée Nintendo',
        'SCMAGLEV and Railway Park', 'Mémorial de la Paix (Hiroshima)', 'Musée d’art contemporain de Kanazawa', 'Yokoo House', 'Yusai-Tei Gallery', 'Naruto Park',
        'teamLab Biovortex Kyoto', 'Château de Himeji + Kokoen', 'Circuit de Suzuka', 'Gion Corner',
        'Musée interactif ninja & samouraï – Tokyo', 'Musée interactif ninja & samouraï – Kyoto', 'Miyako Odori',
    ];

    /** Frais de résa seuls (les entrées se paient sur place). */
    private const FEE_ONLY_PRODUCTS = ['Temple des mousses (Saihoji)', 'Villa impériale de Katsura'];

    private array $products = [];

    private function saleTotal(?string $product, ?float $paTotal): ?float
    {
        if ($product === null) {
            return null;
        }
        if (in_array($product, self::FEE_ONLY_PRODUCTS, true)) {
            return (float) self::RESA_FEE;
        }
        if (in_array($product, self::SITE_TICKET_PRODUCTS, true)) {
            return $paTotal === null ? null : $paTotal + self::RESA_FEE;
        }

        return null;
    }

    public function handle(): int
    {
        ini_set('memory_limit', '-1');
        $gap = (int) $this->option('gap');
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $this->loadProducts();

        $this->info('Lecture de l\'Excel…');
        $reader = IOFactory::createReader('Xlsx');
        $reader->setLoadSheetsOnly(['RÉCAP RÉSAS']);
        $sheet = $reader->load($this->argument('file'))->getActiveSheet();
        $lines = $this->readLines($sheet);
        $this->info(count($lines).' lignes de prestation lues.');

        // 1. Regroupement : un dossier par client et par voyage.
        $byClient = [];
        foreach ($lines as $l) {
            $byClient[$l['client_key']][] = $l;
        }
        $trips = [];
        $limits = [];
        foreach ($byClient as $key => $clientLines) {
            usort($clientLines, fn ($a, $b) => [$a['date'] ?? '9999', $a['row']] <=> [$b['date'] ?? '9999', $b['row']]);
            $current = [];
            $prev = null;
            foreach ($clientLines as $l) {
                if ($prev && $l['date'] && $prev['date']) {
                    $diff = Carbon::parse($prev['date'])->diffInDays(Carbon::parse($l['date']));
                    if ($diff >= 20 && $diff <= 40) {
                        $limits[] = [$l['client_label'], $prev['date'], $l['date'], $diff, $diff > $gap ? 'scindé' : 'regroupé'];
                    }
                    if ($diff > $gap) {
                        $trips[] = $current;
                        $current = [];
                    }
                }
                $current[] = $l;
                if ($l['date']) {
                    $prev = $l;
                }
            }
            if ($current) {
                $trips[] = $current;
            }
        }
        if ($from) {
            $trips = array_values(array_filter($trips, fn ($t) => collect($t)->contains(fn ($l) => $l['date'] && Carbon::parse($l['date'])->gte($from))));
        }

        // 2. Agences : existantes ou à créer.
        $existingAgencies = Agency::pluck('name')->mapWithKeys(fn ($n) => [$this->squash($n) => $n])->all();
        $agenciesNeeded = [];
        foreach ($trips as $t) {
            if ($t[0]['agency']) {
                $agenciesNeeded[$t[0]['agency']] = ($agenciesNeeded[$t[0]['agency']] ?? 0) + 1;
            }
        }
        ksort($agenciesNeeded);

        // 3. Rapprochement avec les dossiers existants (nom ET dates exacts).
        $matches = [];
        foreach (Folder::query()->get() as $f) {
            foreach ($trips as $i => $t) {
                $names = $this->squash($t[0]['first_name']);
                $dates = array_filter(array_column($t, 'date'));
                if ($names !== '' && $this->squash((string) $f->lead_traveler_name) === $names && $dates
                    && $f->start_date && Carbon::parse($f->start_date)->toDateString() === min($dates)) {
                    $matches[] = [$f->reference, $t[0]['client_label'], min($dates)];
                }
            }
        }

        // 4. Produits, statuts, anomalies.
        $statusIds = ItemStatus::pluck('id', 'name')->all();
        $stats = ['product' => [], 'unmatched' => [], 'status' => [], 'anomalies' => []];
        $rowsOut = [];
        foreach ($trips as $ti => $t) {
            foreach ($t as $l) {
                $p = $l['product'];
                $stats['product'][$p ?? 'NON RAPPROCHÉ'] = ($stats['product'][$p ?? 'NON RAPPROCHÉ'] ?? 0) + 1;
                if (! $p) {
                    $stats['unmatched'][$l['label_first']] = ($stats['unmatched'][$l['label_first']] ?? 0) + 1;
                }
                $stats['status'][$l['status'] ?? 'SANS COULEUR'] = ($stats['status'][$l['status'] ?? 'SANS COULEUR'] ?? 0) + 1;
                if (! $l['date']) {
                    $stats['anomalies'][] = [$l['client_label'], $l['row'], 'date de prestation absente ou illisible'];
                }
                if ($l['status'] && ! isset($statusIds[$l['status']])) {
                    $stats['anomalies'][] = [$l['client_label'], $l['row'], "statut introuvable en base : {$l['status']}"];
                }
                $rowsOut[] = [$ti + 1, $t[0]['agency'] ?? 'INTERNE', $l['client_label'], $l['row'], $l['date'], $l['label_first'], $p ?? '', $l['pax'], $l['pa_total'], $l['sale_total'], $l['status'] ?? '', $l['requested_at'], $l['confirmed_at'], $l['invoice_at'], $l['delivered_at']];
            }
        }

        // 5. Rapports (aucune écriture en base).
        $dir = base_path($this->option('report'));
        @mkdir($dir, 0777, true);
        $this->csv("$dir/dossiers-lignes.csv", ['dossier_n', 'agence', 'client', 'ligne_excel', 'date', 'prestation', 'produit', 'pax', 'pa_total', 'pv_calcule', 'statut', 'demande', 'confirmee', 'facture', 'envoyee'], $rowsOut);
        $this->csv("$dir/agences.csv", ['agence', 'dossiers', 'existe_en_base'], array_map(fn ($a, $n) => [$a, $n, isset($existingAgencies[$this->squash($a)]) ? 'oui' : 'à créer'], array_keys($agenciesNeeded), $agenciesNeeded));
        $this->csv("$dir/cas-limites-5-10-jours.csv", ['client', 'derniere_date_avant', 'date_suivante', 'ecart_jours', 'decision'], $limits);
        arsort($stats['unmatched']);
        $this->csv("$dir/non-rapproches.csv", ['libelle', 'lignes'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats['unmatched']), $stats['unmatched']));
        $this->csv("$dir/anomalies.csv", ['client', 'ligne_excel', 'anomalie'], $stats['anomalies']);
        $this->csv("$dir/rattachements-dossiers-existants.csv", ['reference', 'client', 'date_debut'], $matches);

        $internal = count(array_filter($trips, fn ($t) => ! $t[0]['agency']));
        $this->newLine();
        $this->info('=== ESSAI À BLANC (rien n\'a été écrit en base) ===');
        $this->table(['Mesure', 'Valeur'], [
            ['Lignes de prestation', count($rowsOut)],
            ['Clients distincts', count($byClient)],
            ['Dossiers à créer (clients × voyages, écart '.$gap.' j)', count($trips)],
            ['… dont dossiers internes', $internal],
            ['… dont dossiers agence', count($trips) - $internal],
            ['Agences utilisées', count($agenciesNeeded)],
            ['… dont à créer', count(array_filter(array_keys($agenciesNeeded), fn ($a) => ! isset($existingAgencies[$this->squash($a)])))],
            ['Rattachements à un dossier existant (nom + dates exacts)', count($matches)],
            ['Cas limites (écart 20 à 40 j)', count($limits)],
            ['Lignes sans produit (→ lignes libres)', $stats['product']['NON RAPPROCHÉ'] ?? 0],
            ['Anomalies', count($stats['anomalies'])],
        ]);
        arsort($stats['product']);
        $this->table(['Produit', 'Lignes'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats['product']), $stats['product']));
        arsort($stats['status']);
        $this->table(['Statut', 'Lignes'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats['status']), $stats['status']));
        $this->line('Rapports : '.$dir);

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function readLines($sheet): array
    {
        $out = [];
        $last = $sheet->getHighestRow();
        for ($r = 7; $r <= $last; $r++) {
            $raw = trim((string) $sheet->getCell("A$r")->getValue());
            if ($raw === '') {
                continue;
            }
            [$agency, $names] = $this->parseClient($raw);
            $label = $sheet->getCell("C$r")->getValue();
            $labelText = trim((string) $label);
            $norm = Str::lower(Str::ascii($labelText));
            $fill = $sheet->getStyle("A$r")->getFill();
            $rgb = $fill->getFillType() !== 'none' ? strtoupper($fill->getStartColor()->getRGB()) : null;
            $status = $rgb ? (self::COLOR_STATUS[$rgb] ?? null) : null;
            $pink = $rgb === 'F7A3F3';

            $product = $this->matchProduct($norm);
            $paTotal = is_numeric($v = $sheet->getCell("H$r")->getCalculatedValue()) ? (float) $v : null;
            $out[] = [
                'sale_total' => $this->saleTotal($product, $paTotal),
                'row' => $r,
                'agency' => $agency,
                'client_key' => ($agency ?? 'INTERNE').'|'.$this->squash($names),
                'client_label' => str_replace("\n", ' / ', $names).($agency ? " ($agency)" : ' (interne)'),
                'first_name' => trim(explode("\n", $names)[0]),
                'date' => $this->date($sheet->getCell("B$r")->getValue()),
                'label_first' => trim(explode("\n", $labelText)[0]),
                'product' => $product,
                'pax' => is_numeric($v = $sheet->getCell("F$r")->getValue()) ? (int) $v : null,
                'pa_total' => $paTotal,
                'status' => $status,
                'requested_at' => $this->date($sheet->getCell("I$r")->getValue()),
                'confirmed_at' => $this->date($sheet->getCell("J$r")->getValue()),
                'invoice_at' => $pink ? null : $this->date($sheet->getCell("K$r")->getValue()),
                'delivered_at' => $this->date($sheet->getCell("P$r")->getValue()),
            ];
        }

        return $out;
    }

    /** @return array{0: ?string, 1: string} agence canonique (null = interne) et noms des voyageurs */
    private function parseClient(string $raw): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $raw))));
        $agencyRaw = null;
        if ($lines && preg_match('/^\((.+)\)$/u', end($lines), $m)) {
            $agencyRaw = trim($m[1]);
            array_pop($lines);
        } elseif ($lines && preg_match('/^(.*\S)\s*\(([^)]+)\)$/u', end($lines), $m)) {
            $agencyRaw = trim($m[2]);
            $lines[count($lines) - 1] = $m[1];
        }
        $names = implode("\n", $lines);
        if ($agencyRaw === null) {
            return [null, $names];
        }
        $key = $this->squash($agencyRaw);
        if (in_array($key, self::INTERNAL, true)) {
            return [null, $names];
        }

        return [self::ALIASES[$key] ?? self::ALIASES[Str::lower($key)] ?? $agencyRaw, $names];
    }

    private function loadProducts(): void
    {
        foreach (Product::pluck('name') as $name) {
            $this->products[$this->squash($name)] = $name;
        }
    }

    private function matchProduct(string $norm): ?string
    {
        foreach (self::FAMILIES as $product => $re) {
            if (preg_match($re, $norm) && isset($this->products[$this->squash($product)])) {
                return $product;
            }
        }
        // Nom proche d'un produit existant (tous les mots significatifs du produit présents dans le libellé).
        foreach ($this->products as $key => $name) {
            $tokens = array_filter(explode(' ', Str::lower(Str::ascii($name))), fn ($w) => strlen($w) > 3 && ! in_array($w, ['visite', 'guidee', 'journee', 'groupe', 'prive', 'avec', 'dans']));
            $tokens = array_map(fn ($w) => preg_replace('/[^a-z0-9]/', '', $w), $tokens);
            $tokens = array_filter($tokens);
            if (count($tokens) >= 2 && count(array_filter($tokens, fn ($w) => str_contains($norm, $w))) === count($tokens)) {
                return $name;
            }
        }

        return null;
    }

    private function date(mixed $v): ?string
    {
        if (is_numeric($v) && $v > 40000 && $v < 60000) {
            return XlDate::excelToDateTimeObject($v)->format('Y-m-d');
        }

        return null;
    }

    private function squash(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($s)));
    }

    private function csv(string $path, array $head, array $rows): void
    {
        $h = fopen($path, 'w');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, $head, ';');
        foreach ($rows as $row) {
            fputcsv($h, $row, ';');
        }
        fclose($h);
    }
}
