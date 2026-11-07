<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Suggestions de lieu de rendez-vous à partir des hébergements et vols déjà saisis dans le dossier
 * (état courant du formulaire, enregistré ou non). Le choix est copié en texte sur la prestation.
 */
final class MeetingSuggestions
{
    /**
     * @param  array<string|int, array<string, mixed>>  $stays
     * @param  array<string|int, array<string, mixed>>  $flights
     * @return array<string, string> valeur à enregistrer => libellé affiché
     */
    public static function options(?string $serviceDate, array $stays, array $flights): array
    {
        $date = self::date($serviceDate);
        $ranked = [];

        foreach ($stays as $stay) {
            $name = trim((string) ($stay['hotel_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $in = self::date($stay['check_in'] ?? null);
            $out = self::date($stay['check_out'] ?? null);
            $current = $date && $in && $out && $date->gte($in) && $date->lte($out);
            $value = $name . (filled($stay['address'] ?? null) ? ' — ' . trim((string) $stay['address']) : '');
            $label = '🏨 ' . $name . ($stay['city'] ?? null ? ' (' . $stay['city'] . ')' : '')
                . ($in ? ' · ' . $in->format('d/m') . ($out ? ' → ' . $out->format('d/m') : '') : '');
            $ranked[] = [$current ? 0 : 2, $in?->timestamp ?? 0, $value, $current ? $label . ' · ' . __('séjour en cours') : $label];
        }

        foreach ($flights as $flight) {
            $airport = trim((string) ($flight['arrival_airport'] ?? ''));
            if ($airport === '') {
                continue;
            }
            $arrival = self::date($flight['arrival_local'] ?? null, false);
            $number = trim((string) ($flight['flight_number'] ?? ''));
            $time = $arrival ? $arrival->format('d/m H:i') : '';
            $value = 'Aéroport ' . $airport . ($number !== '' ? ' — vol ' . $number : '') . ($time !== '' ? ', arrivée ' . $time : '');
            $same = $date && $arrival && $arrival->isSameDay($date);
            $ranked[] = [$same ? 0 : 3, $arrival?->timestamp ?? 0, $value, '✈️ ' . $value . ($same ? ' · ' . __('même jour') : '')];
        }

        usort($ranked, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $options = [];
        foreach ($ranked as [, , $value, $label]) {
            $options[$value] ??= $label;
        }

        return $options;
    }

    private static function date(mixed $value, bool $startOfDay = true): ?Carbon
    {
        if (blank($value)) {
            return null;
        }
        try {
            $c = Carbon::parse($value);

            return $startOfDay ? $c->startOfDay() : $c;
        } catch (\Throwable) {
            return null;
        }
    }
}
