<?php

namespace App\Exports;

use App\Models\TrainStation;
use App\Support\TransportFareResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Commande IWA : un onglet par mois, selon le mois de DÉBUT DU DOSSIER (et non celui du trajet),
 * pour qu'un dossier à cheval sur deux mois reste entier dans un seul onglet.
 */
class IwaTicketsExport implements WithMultipleSheets
{
    public const MONTHS = [1 => 'Janv', 2 => 'Févr', 3 => 'Mars', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juil', 8 => 'Août', 9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'];

    /** @var Collection<int, array<string, mixed>> */
    protected Collection $routes;

    /** @var array<string, string> name_en => name_ja */
    protected array $jaNames = [];

    public function __construct($routes)
    {
        $this->routes = collect($routes)->map(fn ($r) => (array) $r)->values();
        $this->jaNames = $this->loadJaNames();
    }

    /** @return array<int, IwaMonthSheet> */
    public function sheets(): array
    {
        $rows = $this->routes->sortBy(fn ($r) => [
            self::monthKey($r) === '' ? '9999-99' : self::monthKey($r),
            (string) ($r['folder_start'] ?? ''),
            (int) ($r['folder_id'] ?? 0),
            (string) ($r['date'] ?? ''),
            self::normalizeTime($r['dep_time'] ?? ''),
        ])->values();

        $groups = $rows->groupBy(fn ($r) => self::monthKey($r));

        // Année dans le nom seulement si le même mois existe sur deux années.
        $yearsByMonth = [];
        foreach ($groups->keys() as $key) {
            if ($key !== '') {
                [$y, $m] = explode('-', (string) $key);
                $yearsByMonth[(int) $m][$y] = true;
            }
        }

        $sheets = [];
        foreach ($groups as $key => $groupRows) {
            if ((string) $key === '') {
                $title = 'Dde IWA (sans date)';
            } else {
                [$y, $m] = explode('-', (string) $key);
                $title = 'Dde IWA ' . self::MONTHS[(int) $m]
                    . (count($yearsByMonth[(int) $m] ?? []) > 1 ? ' ' . $y : '');
            }
            $sheets[] = new IwaMonthSheet($title, $groupRows->map(fn ($r) => $this->toRow($r))->all());
        }

        return $sheets ?: [new IwaMonthSheet('Dde IWA', [])];
    }

    /** Mois de rattachement « YYYY-MM » : début du dossier, à défaut date du trajet. */
    public static function monthKey(array $row): string
    {
        foreach (['folder_start', 'date'] as $k) {
            if (! empty($row[$k])) {
                try {
                    return Carbon::parse($row[$k])->format('Y-m');
                } catch (\Throwable $e) {
                }
            }
        }

        return '';
    }

    /** @return array<int, mixed> */
    protected function toRow(array $r): array
    {
        $date = '';
        if (! empty($r['date'])) {
            try {
                $date = (float) \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(Carbon::parse($r['date'])->startOfDay());
            } catch (\Throwable $e) {
            }
        }

        return [
            trim((string) ($r['pax_name'] ?? '')) . '様',
            $date,
            self::detailCell($r, $this->jaNames),
            self::normalizeTime($r['dep_time'] ?? ''),
            self::normalizeTime($r['arr_time'] ?? ''),
            self::passengersCell($r),
        ];
    }

    /** Cellule D : train / « A - B » (japonais) / classe [－ demande] / 乗車券 X～Y (japonais). */
    public static function detailCell(array $r, array $jaNames = []): string
    {
        $dep = (string) ($r['departure'] ?? '');
        $arr = (string) ($r['arrival'] ?? '');

        $class = trim((string) ($r['class'] ?? ''));
        $request = trim(preg_replace('/\s+/u', ' ', (string) ($r['specific_requests'] ?? '')));
        if ($request !== '') {
            $class .= '－' . $request;
        }

        $lines = [
            trim((string) ($r['train_name'] ?? '')),
            self::jaName($dep, $jaNames) . ' - ' . self::jaName($arr, $jaNames),
            $class,
        ];

        $mode = $r['fare_ticket_mode'] ?? 'same';
        if ($mode !== 'none') {
            $from = $dep;
            $to = $arr;
            if ($mode === 'custom' && filled($r['fare_ticket_from'] ?? null) && filled($r['fare_ticket_to'] ?? null)) {
                $from = (string) $r['fare_ticket_from'];
                $to = (string) $r['fare_ticket_to'];
            }
            $lines[] = '乗車券 ' . self::jaName($from, $jaNames) . '～' . self::jaName($to, $jaNames);
        }

        return implode("\n", $lines);
    }

    /** « Hakata Station (Gare Shinkansen) » => « Hakata ». */
    public static function shortName(string $name): string
    {
        $name = preg_replace('/\s*[\(（][^\)）]*[\)）]/u', '', $name);
        $name = preg_replace('/\bStation\b/iu', '', $name);
        $name = preg_replace('/\bSta\.(?=\s|$)/iu', '', $name);

        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    /** Nom japonais d'une gare ou d'un arrêt (name_ja, sans 駅) ; à défaut, nom anglais court. Lignes 2 et 4 de D. */
    public static function jaName(string $nameEn, array $jaNames): string
    {
        $ja = trim((string) ($jaNames[$nameEn] ?? ''));
        if ($ja === '') {
            return self::shortName($nameEn);
        }

        return preg_replace('/駅$/u', '', $ja);
    }

    /** 2 adultes => 2 ; avec enfant payant => « 大 2 / 小 1 ». */
    public static function passengersCell(array $r): int|string
    {
        $p = TransportFareResolver::passengers([
            'pax_count' => $r['pax_count'] ?? 1,
            'children_count' => $r['children_count'] ?? 0,
            'young_children_count' => $r['young_children_count'] ?? 0,
            'infants_count' => $r['infants_count'] ?? 0,
        ]);

        return $p['children_paying'] > 0 ? "大 {$p['adults']} / 小 {$p['children_paying']}" : $p['adults'];
    }

    public static function normalizeTime($time): string
    {
        $time = trim((string) $time);

        return preg_match('/^\d:\d{2}$/', $time) ? '0' . $time : $time;
    }

    /** @return array<string, string> */
    protected function loadJaNames(): array
    {
        $names = [];
        foreach ($this->routes as $r) {
            foreach (['departure', 'arrival', 'fare_ticket_from', 'fare_ticket_to'] as $k) {
                if (filled($r[$k] ?? null)) {
                    $names[$r[$k]] = true;
                }
            }
        }
        if (! $names) {
            return [];
        }

        $list = array_keys($names);
        $map = [];
        try {
            // Arrêts de bus d'abord : une gare de même nom l'emporte.
            $map = \App\Models\BusStation::whereIn('name_en', $list)->whereNotNull('name_ja')->pluck('name_ja', 'name_en')->all();
        } catch (\Throwable $e) {
        }
        try {
            $map = TrainStation::whereIn('name_en', $list)->whereNotNull('name_ja')->pluck('name_ja', 'name_en')->all() + $map;
        } catch (\Throwable $e) {
        }

        return $map;
    }
}
