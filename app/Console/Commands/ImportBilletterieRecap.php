<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\Folder;
use App\Models\ItemStatus;
use App\Models\Product;
use App\Models\ProductOption;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

/**
 * Reprise de l'onglet « RÉCAP RÉSAS » de l'Excel billetterie : essai à blanc par défaut, écriture réelle avec --apply.
 * Lit l'Excel, regroupe les lignes en dossiers (un par client et par voyage), rapproche agences,
 * produits et statuts, puis écrit des rapports. N'écrit RIEN dans la base sans --apply.
 * Feu vert de Maxime reçu le 2026-11-12 pour l'import réel.
 */
class ImportBilletterieRecap extends Command
{
    protected $signature = 'billetterie:import-recap
        {file : Fichier Excel « [2026] Billetterie »}
        {--gap=30 : Écart maximal (jours) entre deux prestations d\'un même voyage}
        {--apply : ÉCRIT en base (par défaut : essai à blanc, rien n\'est écrit)}
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
        'Pocket wifi' => '/wifi|pocket|e-?sim/',
        'Traduction de permis' => '/traduction de permis|permis/',
        'Location de voiture' => '/location de voiture|toyota rent/',
        'Véhicule privé' => '/vehicule prive|minivan|sk limo|taxi|chauffeur/',
        'Universal Studios Japan (USJ)' => '/\busj\b|universal/',
        'Parc Ghibli' => '/ghibli/',
        'teamLab Planets Tokyo' => '/team ?lab planet/',
        'teamLab Borderless' => '/teamlab borderless/',
        'teamLab Biovortex Kyoto' => '/biovortex/',
        'Tokyo DisneySea' => '/disney ?sea/',
        'Tokyo Disneyland' => '/disneyland/',
        'Koya Pass' => '/koya ?pass|nankai koya/',
        'Nankai Rapi:t' => '/rapit|rapi t/',
        'Miyako Odori' => '/miyako odori/',
        'Musée international du manga de Kyoto' => '/musee (du )?manga|manga museum/',
        'Musée Gion Kagai' => '/gion kagai/',
        'Gion Corner' => '/gion corner/',
        'Villa impériale de Katsura' => '/katsura/',
        'Chichu Art Museum (Naoshima)' => '/chichu/',
        'Lee Ufan Museum (Naoshima)' => '/lee ul?fan/',
        'Musée Ando (Naoshima)' => '/ando museum|musee ando/',
        'Visite nocturne du cimetière Okuno-in – Koya-san' => '/okuno/',
        // Ateliers et activités (la ville est lue dans le libellé complet ou déduite du prestataire)
        'Atelier de forge – Studio NIN (Yase, Kyoto)' => '/forge nin|studio nin/',
        'Affûtage et emmanchage de couteau – Osaka' => '/affutage|forge sakai|sakai/',
        'Visite d’une forge avec confection de lame – Kameoka (Kyoto)' => '/kameoka/',
        'Forge de lame – Otsu (Kyoto)' => '/forge.*(tanaka|otsu)/s',
        'Atelier de confection de kimonos avec déjeuner – Yosano (Kyoto)' => '/ateliers? (de )?confection de kimono/',
        'Initiation au bonsaï – Tokyo' => '/bonsai/',
        'Bambouseraie Takumikuno et atelier au choix – Takamatsu' => '/takumikumo/',
        'Tour des temples et jardins – Kamakura' => '/kamakura/',
        'Cours de cuisine okonomiyaki – Hiroshima' => '/cuisine.*hiroshima|hiroshima.*cuisine/s',
        'Cours de cuisine – Osaka' => '/cuisine.*(osaka|namba)/s',
        'Cours de cuisine – Tokyo' => '/cuisine.*tokyo|sushi.*tokyo|wagyu|sakura cook/s',
        'Cours de cuisine – Kyoto' => '/cuisine.*kyoto|izakaya|bento/s',
        'Calligraphie – Tokyo' => '/calligraph.*(tokyo|jidaiya)/s',
        'Calligraphie – Kyoto' => '/calligraph.*(kyoto|ami)/s',
        'Origami – Tokyo' => '/origami.*tokyo/s',
        'Origami – Kyoto' => '/origami.*(kyoto|wak japan)/s',
        'Ikebana – Tokyo' => '/ikebana.*(tokyo|jidaiya)/s',
        'Ikebana – Kyoto' => '/ikebana.*kyoto/s',
        'Danse du sabre (kenbu) – Kyoto' => '/kenbu|samurai experience/',
        'Spectacle de shamisen privé – Kyoto' => '/shamisen/',
        'Spectacle de rakugo privé en français – Kyoto' => '/rakugo/',
        'Atelier de kintsugi – Tokyo' => '/kintsugi/',
        'Journée thème matcha – Wazuka' => '/wazuka/',
        'Kayak dans la baie – Miyajima' => '/kayak/',
        'Dégustation de saké – Fushimi (Kyoto)' => '/sake/',
        'Dégustation et découverte du thé japonais en français – Kyoto' => '/degustation.*the.*francais/s',
        'Sumo – Démonstration et repas (déjeuner ou dîner) – Tokyo' => '/sumo/',
        // Geisha / maiko / cérémonies du thé
        'Cérémonie du thé avec une maiko ou une geisha – Kyoto' => '/c.{1,3}monie du the.*(maiko|geisha)/s',
        'Cérémonie du thé – Kanazawa' => '/c.{1,3}monie du the.*(kanazawa|gyokusen)/s',
        'Cérémonie du thé – Tokyo' => '/c.{1,3}monie du the.*tokyo/s',
        'Cérémonie du thé – Kyoto' => '/c.{1,3}monie du the|option kimono/',
        'Dîner avec une maiko ou une geisha – Kanazawa' => '/(diner|dejeuner).*(geisha|maiko).*kanazawa|diner maiko kanazawa/s',
        'Dîner avec une maiko ou une geisha – Kyoto' => '/diner.*(geisha|maiko)/s',
        'Musée et performance geisha & maiko – Kyoto' => '/spectacle de maiko|spectacle de geisha|spectacle maiko/',
        // Transports et locations
        'Train Kurobe Torokko' => '/torokko/',
        'Ferry' => '/ferry/',
        'Location de vélo' => '/location de velo|e-?bike/',
        // Billetterie de sites (prix de vente = PA + frais de résa)
        'Tokyo Skytree' => '/sky ?tree/',
        'Temple des mousses (Saihoji)' => '/temple des mousses|saihoji/',
        'Edo Wonderland' => '/edo wonderland/',
        'Naoshima New Museum of Art' => '/(naoshima )?new museum/',
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
        'Château de Himeji + Kokoen' => '/himeji/',
        'Circuit de Suzuka' => '/suzuka/',
        'Musée interactif ninja & samouraï – Tokyo' => '/musee ninja( asakusa)? tokyo/',
        'Musée interactif ninja & samouraï – Kyoto' => '/musee ninja kyoto|musee ninja&|musee ninja$/',
        'Shibuya Sky' => '/shibuya sky/',
        'Tokyo Tower' => '/tokyo tower/',
        'Kobe Port Tower' => '/kobe port tower/',
        'Tsutenkaku (Osaka)' => '/tsutenkaku/',
        'Observatoire Enoura' => '/enoura/',
        'Toei Kyoto Studio Park' => '/toei kyoto/',
        'Miho Museum' => '/miho museum/',
        'Musée Nezu' => '/musee nezu/',
        'Musée national de Tokyo' => '/musee national de tokyo/',
        'Fukuda Art Museum' => '/fukuda/',
        'teamLab Botanical Garden Osaka' => '/botanical garden/',
        'Sanrio Puroland' => '/sanrio/',
        'Pokémon Café' => '/pokemon cafe/',
        'Fuji Speedway' => '/fuji speedway/',
        'Musée Fuji Motors Sport' => '/fuji motors/',
        'Nabegataki Falls' => '/nabegataki/',
        'Archives du Cœur' => '/archives du c/',
        'Hida no Sato' => '/hida no sato/',
        'Art House Project (Naoshima)' => '/art house project/',
        'Balade en hélicoptère – Tokyo / Osaka / Mont Fuji' => '/helicopt/',
        'Hakone freepass' => '/hakone/',
        'Ticket de train / bus' => '/shinkansen|train|express|fare ticket|free ?pass|\bbus\b|nohi|kintetsu|tobu|skyliner|spacia|rapid mie|keisei|romance|nozomi|hikari|thunderbird|\bpass\b|\bjr\b/',
    ];

    /** Billetterie de sites : prix de vente = PA total + frais de résa (règle de Maxime). */
    private const RESA_FEE = 3000;

    private const SITE_TICKET_PRODUCTS = [
        'Universal Studios Japan (USJ)', 'Parc Ghibli', 'teamLab Planets Tokyo', 'teamLab Borderless', 'Tokyo DisneySea', 'Tokyo Disneyland',
        'Musée international du manga de Kyoto', 'Musée Gion Kagai', 'Chichu Art Museum (Naoshima)', 'Lee Ufan Museum (Naoshima)', 'Musée Ando (Naoshima)',
        'Tokyo Skytree', 'Shibuya Sky', 'Tokyo Tower', 'Kobe Port Tower', 'Tsutenkaku (Osaka)', 'Observatoire Enoura', 'Toei Kyoto Studio Park', 'Miho Museum', 'Musée Nezu', 'Musée national de Tokyo', 'Fukuda Art Museum', 'teamLab Botanical Garden Osaka', 'Sanrio Puroland', 'Pokémon Café', 'Fuji Speedway', 'Musée Fuji Motors Sport', 'Nabegataki Falls', 'Archives du Cœur', 'Hida no Sato', 'Art House Project (Naoshima)', 'Edo Wonderland', 'Naoshima New Museum of Art', 'Snoopy Museum Tokyo', 'Aquarium d’Osaka (Kaiyukan)', 'Fuji-Q Highland', 'Legoland Japan',
        'Croisière avec dîner – Yakatabune (Tokyo)', 'Teshima Art Museum', 'Benesse House & Valley Gallery', 'Hiroshi Sugimoto Gallery', 'Musée Kusama Yayoi', 'Musée Nintendo',
        'SCMAGLEV and Railway Park', 'Mémorial de la Paix (Hiroshima)', 'Musée d’art contemporain de Kanazawa', 'Yokoo House', 'Yusai-Tei Gallery', 'Naruto Park',
        'teamLab Biovortex Kyoto', 'Château de Himeji + Kokoen', 'Circuit de Suzuka', 'Gion Corner',
        'Musée interactif ninja & samouraï – Tokyo', 'Musée interactif ninja & samouraï – Kyoto', 'Miyako Odori',
    ];

    /** Frais de résa seuls (les entrées se paient sur place). */
    private const FEE_ONLY_PRODUCTS = ['Temple des mousses (Saihoji)', 'Villa impériale de Katsura'];

    private array $products = [];

    /** @var array<string, true> produits désactivés (clés « aplaties ») */
    private array $inactive = [];

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

        if ($this->option('apply')) {
            $this->warn('=== ÉCRITURE RÉELLE EN BASE (--apply) ===');
            $result = $this->apply($trips);
            $this->table(['Écrit', 'Valeur'], array_map(fn ($k, $v) => [$k, $v], array_keys($result), $result));
        } else {
            $this->comment('Essai à blanc : rien n\'a été écrit. Relancer avec --apply pour écrire.');
        }

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
            // Décision de Maxime : « Visite de la forge à Tokyo en privé / Japanism » = prestation annulée.
            if (str_contains($norm, 'forge a tokyo en prive') && str_contains($norm, 'japanism')) {
                $status = 'Annulé';
            }
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
                'label_full' => $labelText,
                'dep' => $sheet->getCell("D$r")->getValue(),
                'arr' => $sheet->getCell("E$r")->getValue(),
                'pa_unit' => is_numeric($v = $sheet->getCell("G$r")->getCalculatedValue()) ? (float) $v : null,
                'guide_info' => trim((string) $sheet->getCell("M$r")->getValue()),
                'send_place' => trim((string) $sheet->getCell("N$r")->getValue()),
                'hotel' => trim((string) $sheet->getCell("O$r")->getValue()),
                'contact' => trim((string) $sheet->getCell("Q$r")->getValue()),
            ];
        }

        return $out;
    }

    /**
     * Écriture réelle (option --apply) : agences manquantes, dossiers, prestations.
     * Écrit par requêtes directes (aucun événement Eloquent : ni mission guide, ni Calendar, ni Google Drive,
     * ni historique) dans UNE transaction. Idempotent : un dossier déjà importé (import_key) est ignoré.
     * Dossiers internes : chaque prestation passe par une prestation préparée convertie (règle du trigger SQL).
     *
     * @return array<string, int>
     */
    private function apply(array $trips): array
    {
        $now = now();
        $stats = ['dossiers' => 0, 'dossiers_internes' => 0, 'prestations' => 0, 'agences_creees' => 0, 'deja_importes' => 0, 'lignes_sans_date' => 0];
        $statusIds = ItemStatus::pluck('id', 'name')->all();
        $defaultStatus = $statusIds['En attente de validation'] ?? null;
        $products = Product::orderBy('id')->get(['id', 'name', 'supplier_id'])->keyBy('name');
        $agencyIds = Agency::pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [$this->squash($n) => $id])->all();
        $today = Carbon::today()->toDateString();

        DB::transaction(function () use ($trips, $now, &$stats, $statusIds, $defaultStatus, $products, &$agencyIds, $today) {
            foreach ($trips as $trip) {
                $first = $trip[0];
                $dated = array_values(array_filter($trip, fn ($l) => $l['date']));
                $stats['lignes_sans_date'] += count($trip) - count($dated);
                if (! $dated) {
                    continue;
                }
                $dates = array_column($dated, 'date');
                sort($dates);
                $key = 'recap:'.substr(sha1($first['client_key'].'|'.$dates[0]), 0, 20);
                if (DB::table('folders')->where('import_key', $key)->exists()) {
                    $stats['deja_importes']++;

                    continue;
                }

                $internal = ! $first['agency'];
                $agencyId = null;
                if (! $internal) {
                    $sq = $this->squash($first['agency']);
                    if (! isset($agencyIds[$sq])) {
                        $agencyIds[$sq] = DB::table('agencies')->insertGetId([
                            'client_group_id' => 1,
                            'name' => $first['agency'],
                            'email' => 'a-completer+'.Str::slug($first['agency']).'@takada-travel.invalid',
                            'is_approved' => 0,
                            'import_key' => 'recap',
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                        $stats['agences_creees']++;
                    }
                    $agencyId = $agencyIds[$sq];
                }

                $allCancelled = collect($dated)->every(fn ($l) => $l['status'] === 'Annulé');
                $end = max($dates);
                $status = $allCancelled ? 'cancelled' : ($end < $today ? 'completed' : 'confirmed');
                $opsStatus = $allCancelled ? 'annule' : ($end < $today ? 'termine' : 'reservations_en_cours');
                $hotel = collect($dated)->pluck('hotel')->map(fn ($h) => trim(explode("\n", (string) $h)[0]))->first(fn ($h) => $h !== '' && ! in_array(Str::lower($h), ['×', 'x'], true));
                $send = Str::lower(Str::ascii((string) collect($dated)->pluck('send_place')->first(fn ($s) => $s !== '' && ! in_array(Str::lower($s), ['×', 'x'], true))));
                $dispatch = $send === '' || str_contains($send, 'hotel') ? 'hotel' : (str_contains($send, 'guide') ? 'guide' : 'autre');
                $contact = collect($dated)->pluck('contact')->first(fn ($c) => $c !== '');
                $names = str_replace("\n", ' / ', explode('(', $first['client_label'])[0]);

                do {
                    $reference = 'TKD-'.str_replace('-', '', substr($dates[0], 0, 7)).'-'.strtoupper(Str::random(4));
                } while (DB::table('folders')->where('reference', $reference)->exists());

                $folderId = DB::table('folders')->insertGetId([
                    'reference' => $reference,
                    'origin' => $internal ? 'internal' : 'b2b',
                    'kind' => $internal ? 'services_only' : null,
                    'agency_id' => $agencyId,
                    'segment' => $internal ? null : 'billetterie',
                    'folder_name' => Str::limit(trim($names), 250, ''),
                    'lead_traveler_name' => Str::limit($first['first_name'], 250, ''),
                    'hotel_booking_name' => $hotel ? Str::limit($hotel, 250, '') : null,
                    'first_hotel_name' => $hotel ? Str::limit($hotel, 250, '') : null,
                    'pax_adults' => max(1, (int) max(array_map(fn ($l) => (int) $l['pax'], $dated))),
                    'pax_children' => 0,
                    'start_date' => $dates[0],
                    'end_date' => $end,
                    'status' => $status,
                    'ops_status' => $internal ? $opsStatus : null,
                    'ticket_dispatch_method' => $dispatch,
                    'internal_notes' => $contact ? 'Contact client (import récap) : '.Str::limit($contact, 400, '') : null,
                    'import_key' => $key,
                    // Reprise d'un historique déjà traité : jamais dans les listes d'étiquettes à exporter.
                    'label_exported_at' => $now,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $stats['dossiers']++;
                $stats['dossiers_internes'] += $internal ? 1 : 0;

                $billable = 0;
                foreach ($dated as $l) {
                    $product = $l['product'] ? $products->get($l['product']) : null;
                    $qty = max(1, (int) ($l['pax'] ?? 1));
                    $sale = (int) round($l['sale_total'] ?? 0);
                    $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $l['label_full']))));
                    $notes = implode(' / ', $lines);
                    if ($l['guide_info'] !== '' && ! in_array(Str::lower($l['guide_info']), ['×', 'x'], true)) {
                        $notes .= ' | Infos guide/chauffeur : '.$l['guide_info'];
                    }
                    [$routeFrom, $meetTime] = $this->routePart($l['dep']);
                    [$routeTo, $endTime] = $this->routePart($l['arr']);
                    $endDate = is_numeric($l['arr']) && $l['arr'] > 40000 ? $this->date($l['arr']) : null;
                    $row = [
                        'product_id' => $product?->id,
                        'supplier_id' => $product?->supplier_id,
                        'service_date' => $l['date'],
                        'quantity' => $qty,
                        'pax_adults' => $qty, 'pax_children' => 0, 'pax_teenagers' => 0,
                        'unit_price' => (int) round($sale / $qty),
                        'total_price' => $sale,
                        'purchase_unit_price' => (int) round($l['pa_unit'] ?? 0),
                        'purchase_total_price' => (int) round($l['pa_total'] ?? 0),
                        'title' => $product ? null : Str::limit($l['label_first'], 250, ''),
                        'is_internal' => 0,
                        'ticketing_notes' => Str::limit($notes, 2000, ''),
                        'meeting_time' => $meetTime, 'end_time' => $endTime,
                        'route_from' => $routeFrom, 'route_to' => $routeTo, 'end_date' => $endDate,
                    ];
                    $ref = $key.':'.$l['row'];
                    $itemRow = $row + [
                        'folder_id' => $folderId,
                        'item_status_id' => $statusIds[$l['status']] ?? $defaultStatus,
                        'requested_at' => $l['requested_at'], 'supplier_confirmed_at' => $l['confirmed_at'],
                        'invoice_received_at' => $l['invoice_at'], 'delivered_at' => $l['delivered_at'],
                        'import_ref' => $ref,
                        'label_exported_at' => $now,
                        'created_at' => $now, 'updated_at' => $now,
                    ];

                    if ($internal) {
                        $plannedId = DB::table('folder_planned_items')->insertGetId($row + [
                            'folder_id' => $folderId, 'state' => 'draft', 'source' => 'import_recap', 'source_ref' => $ref,
                            'sale_price_overridden' => 1, 'purchase_price_overridden' => 1, 'lock_version' => 0,
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                        $itemRow['planned_item_id'] = $plannedId;
                    }
                    $itemId = DB::table('folder_items')->insertGetId($itemRow);
                    if ($internal) {
                        DB::table('folder_planned_items')->where('id', $plannedId)->update(['state' => 'converted', 'folder_item_id' => $itemId, 'converted_at' => $now]);
                        $stats['prestations_preparees'] = ($stats['prestations_preparees'] ?? 0) + 1;
                    }
                    $stats['prestations']++;
                    if ($l['status'] !== 'Annulé') {
                        $billable += $sale;
                    }
                }
                DB::table('folders')->where('id', $folderId)->update(['total_price' => $billable]);
            }
        });

        return $stats;
    }

    /** @return array{0: ?string, 1: ?string} texte de lieu et heure « HH:MM » extraits d'une cellule départ/arrivée */
    private function routePart(mixed $v): array
    {
        if ($v === null || $v === '') {
            return [null, null];
        }
        if (is_numeric($v)) {
            return $v > 0 && $v < 1 ? [null, XlDate::excelToDateTimeObject($v)->format('H:i')] : [null, null];
        }
        $text = trim((string) $v);
        if (in_array(Str::lower($text), ['×', 'x'], true)) {
            return [null, null];
        }
        $time = null;
        if (preg_match('/\b(\d{1,2})\s*[h:]\s*(\d{2})?\b/u', $text, $m)) {
            $time = sprintf('%02d:%02d', min(23, (int) $m[1]), min(59, (int) ($m[2] ?? 0)));
        }

        return [Str::limit(str_replace("\n", ' / ', $text), 150, ''), $time];
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
        foreach (Product::orderBy('id')->get(['name', 'is_public']) as $p) {
            $this->products[$this->squash($p->name)] = $p->name;
            if (! $p->is_public) {
                $this->inactive[$this->squash($p->name)] = true;
            }
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
            // Le rapprochement par nom proche ne vise que des produits ACTIFS (les produits créés pour la billetterie
            // passent par les règles explicites ci-dessus).
            if (isset($this->inactive[$key])) {
                continue;
            }
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
