<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\TransportFare;
use App\Models\TransportFareSeason;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Import du modèle « modele-grille-tarifs-trains.xlsx » (onglets « Tarifs » et « Calendrier saisons »).
 *
 * Chaque ligne du modèle (trajet × train) devient une ligne de grille par classe remplie.
 * Les lignes importées précédemment (source « import-excel ») sont remplacées ; les tarifs saisis
 * à la main dans le portail sont conservés. Le calendrier est remplacé s'il contient au moins une période.
 */
class TransportFareImporter
{
    public const SOURCE = 'import-excel';

    // Colonnes de l'onglet « Tarifs » (1 = A), en-têtes sur 2 lignes, données à partir de la ligne 3.
    private const COL = [
        'publish' => 2, 'station_a' => 4, 'station_b' => 6, 'train' => 7, 'mode' => 9,
        'reserved' => [11, 12, 13, 14], 'non_reserved' => 15, 'green' => [16, 17, 18, 19],
        'gran_class' => 20, 'flat' => 21, 'child' => 22, 'valid_from' => 23, 'valid_to' => 24, 'notes' => 29,
    ];

    /** @return array{fares:int, rows:int, seasons:int, skipped: array<int, string>} */
    public function import(string $path, bool $dryRun = false): array
    {
        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('Tarifs');
        if (! $sheet) {
            throw new \RuntimeException(__('Onglet « Tarifs » introuvable : utilisez le modèle de grille tarifaire.'));
        }

        $options = $this->classOptions();
        $fares = [];
        $skipped = [];
        $rowsWithPrice = 0;

        for ($r = 3, $max = $sheet->getHighestDataRow(); $r <= $max; $r++) {
            $a = $this->text($sheet, self::COL['station_a'], $r);
            $b = $this->text($sheet, self::COL['station_b'], $r);
            if ($a === '' && $b === '') {
                continue;
            }
            if (mb_strtolower($this->text($sheet, self::COL['publish'], $r)) === 'non') {
                continue;
            }

            $found = $this->faresForRow($sheet, $r, $options);
            if ($found === []) {
                continue; // aucune case de prix remplie : le trajet reste sur devis
            }
            if ($a === '' || $b === '' || str_starts_with($a, '⚠') || str_starts_with($b, '⚠')) {
                $skipped[$r] = __('gare non reconnue dans le portail (colonnes « dans le portail »)');
                continue;
            }

            $train = $this->text($sheet, self::COL['train'], $r);
            $common = [
                'departure_station' => $a,
                'arrival_station' => $b,
                'both_directions' => true,
                'train_name' => ($train === '' || $train === '(tous)') ? null : $train,
                'valid_from' => $this->date($sheet, self::COL['valid_from'], $r),
                'valid_to' => $this->date($sheet, self::COL['valid_to'], $r),
                'is_active' => true,
                'source' => self::SOURCE,
                'notes' => $this->text($sheet, self::COL['notes'], $r) ?: null,
            ];
            foreach ($found as $fare) {
                $fares[] = $common + $fare;
            }
            $rowsWithPrice++;
        }

        $seasons = $this->seasons($book->getSheetByName('Calendrier saisons'));

        if (! $dryRun) {
            DB::transaction(function () use ($fares, $seasons) {
                TransportFare::where('source', self::SOURCE)->delete();
                foreach ($fares as $fare) {
                    TransportFare::create($fare);
                }
                if ($seasons !== []) {
                    TransportFareSeason::query()->delete();
                    foreach ($seasons as $season) {
                        TransportFareSeason::create($season);
                    }
                }
            });
        }

        return ['fares' => count($fares), 'rows' => $rowsWithPrice, 'seasons' => count($seasons), 'skipped' => $skipped];
    }

    /** @return array<int, array<string, mixed>> une entrée par classe remplie */
    private function faresForRow(Worksheet $sheet, int $r, array $options): array
    {
        $out = [];
        $flatMode = str_contains(mb_strtolower($this->text($sheet, self::COL['mode'], $r)), 'unique');
        $child = $this->int($sheet, self::COL['child'], $r);

        if ($flatMode) {
            $flat = $this->int($sheet, self::COL['flat'], $r);
            if ($flat && $options['reserve']) {
                $out[] = ['product_option_id' => $options['reserve'], 'pricing_mode' => TransportFare::MODE_FLAT, 'price_flat' => $flat, 'child_price' => $child];
            }

            return $out;
        }

        foreach (['reserve' => self::COL['reserved'], 'green' => self::COL['green']] as $class => $cols) {
            $prices = array_map(fn ($c) => $this->int($sheet, $c, $r), $cols);
            if (! array_filter($prices) || ! $options[$class]) {
                continue;
            }
            $out[] = [
                'product_option_id' => $options[$class],
                'pricing_mode' => TransportFare::MODE_SEASONAL,
                'price_low' => $prices[0], 'price_normal' => $prices[1], 'price_high' => $prices[2], 'price_peak' => $prices[3],
                'child_price' => $class === 'reserve' ? $child : null,
            ];
        }
        foreach (['non_reserve' => self::COL['non_reserved'], 'gran_class' => self::COL['gran_class']] as $class => $col) {
            $price = $this->int($sheet, $col, $r);
            if ($price && $options[$class]) {
                $out[] = ['product_option_id' => $options[$class], 'pricing_mode' => TransportFare::MODE_FLAT, 'price_flat' => $price];
            }
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function seasons(?Worksheet $sheet): array
    {
        if (! $sheet) {
            return [];
        }
        $out = [];
        for ($r = 2, $max = $sheet->getHighestDataRow(); $r <= $max; $r++) {
            $company = $this->text($sheet, 1, $r);
            if (str_starts_with($company, '(exemple)')) {
                continue;
            }
            $season = $this->text($sheet, 2, $r);
            $from = $this->date($sheet, 3, $r);
            $to = $this->date($sheet, 4, $r);
            if (! in_array($season, ['low', 'high', 'peak', 'basse', 'haute', 'tres_haute'], true) || ! $from || ! $to) {
                continue;
            }
            $out[] = [
                'company' => $company ?: null,
                'season' => ['basse' => 'low', 'haute' => 'high', 'tres_haute' => 'peak'][$season] ?? $season,
                'starts_on' => min($from, $to),
                'ends_on' => max($from, $to),
                'notes' => $this->text($sheet, 5, $r) ?: null,
            ];
        }

        return $out;
    }

    /** Options « classe » du produit billet : non réservée, réservée, Green, Gran Class. */
    private function classOptions(): array
    {
        $productIds = Product::where('product_type', 'transport')->pluck('id');
        $map = ['non_reserve' => null, 'reserve' => null, 'green' => null, 'gran_class' => null];
        foreach (ProductOption::whereIn('product_id', $productIds)->orderBy('id')->get() as $opt) {
            $name = mb_strtolower($opt->name);
            $class = match (true) {
                str_contains($name, 'gran') => 'gran_class',
                str_contains($name, 'green') => 'green',
                str_contains($name, 'non réserv') || str_contains($name, 'non reserv') => 'non_reserve',
                str_contains($name, 'réserv') || str_contains($name, 'reserv') => 'reserve',
                default => null,
            };
            if ($class && ! $map[$class]) {
                $map[$class] = $opt->id;
            }
        }

        return $map;
    }

    private function text(Worksheet $sheet, int $col, int $row): string
    {
        return trim((string) $sheet->getCell([$col, $row])->getFormattedValue());
    }

    private function int(Worksheet $sheet, int $col, int $row): ?int
    {
        $v = $sheet->getCell([$col, $row])->getCalculatedValue();
        if (is_string($v)) {
            $v = preg_replace('/[^\d]/', '', $v);
        }

        return ($v === null || $v === '' || (int) $v <= 0) ? null : (int) round((float) $v);
    }

    private function date(Worksheet $sheet, int $col, int $row): ?string
    {
        $v = $sheet->getCell([$col, $row])->getValue();
        if ($v === null || $v === '') {
            return null;
        }
        try {
            return is_numeric($v)
                ? Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))->toDateString()
                : Carbon::parse((string) $v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
