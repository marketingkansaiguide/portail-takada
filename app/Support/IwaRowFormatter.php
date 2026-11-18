<?php

namespace App\Support;

use App\Models\TrainStation;
use App\Support\TransportFareResolver;

/** Mise en forme des lignes d'une commande IWA (cellule détail, gares en japonais, voyageurs, heures). */
class IwaRowFormatter
{
    public const AGENCY_BLOCK = "TAKADA TRAVEL合同会社\n06-6195-9799\n担当者：ノロ/シンディ/田中";

    /** Cellule détail : train / « A - B » (japonais) / classe [－ demande] / 乗車券 X～Y (japonais). */
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
            $lines[] = '乗車券' . self::jaName($from, $jaNames) . '～' . self::jaName($to, $jaNames);
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

        return $p['children_paying'] > 0 ? "大　{$p['adults']} / 小　{$p['children_paying']}" : $p['adults'];
    }

    public static function normalizeTime($time): string
    {
        $time = trim((string) $time);

        return preg_match('/^\d:\d{2}$/', $time) ? '0' . $time : $time;
    }


    /**
     * Noms japonais (name_en => name_ja) des gares et arrêts cités par les lignes.
     *
     * @param  iterable<array<string, mixed>>  $routes
     * @return array<string, string>
     */
    public static function loadJaNames(iterable $routes): array
    {
        $names = [];
        foreach ($routes as $r) {
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
