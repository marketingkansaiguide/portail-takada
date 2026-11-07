<?php

namespace App\Support;

use App\Models\Folder;
use App\Models\FolderPlannedItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Chronologie d'un dossier, jour par jour : nuits, vols, prestations préparées (statut distinct)
 * et commandées, plus quelques contrôles de cohérence simples. Lecture seule, aucun calcul métier.
 */
final class TripTimeline
{
    /** Catégories pour lesquelles un horaire de rendez-vous est attendu. */
    private const TIME_EXPECTED = ['Guidage', 'Transferts aéroport'];

    /**
     * @return array{days: array<string, array{date: Carbon, entries: array<int, array<string, mixed>>}>, alerts: array<int, string>}
     */
    public static function build(Folder $folder): array
    {
        $folder->loadMissing(['stays', 'flightSegments']);
        $planned = FolderPlannedItem::with('product.category')->where('folder_id', $folder->id)
            ->where('state', FolderPlannedItem::STATE_DRAFT)->get();
        $ordered = $folder->folderItems()->with(['product.category', 'itemStatus'])->get();

        $days = [];
        $add = function (?Carbon $date, array $entry) use (&$days): void {
            if (! $date) {
                return;
            }
            $key = $date->format('Y-m-d');
            $days[$key] ??= ['date' => $date->copy()->startOfDay(), 'entries' => []];
            $days[$key]['entries'][] = $entry;
        };

        foreach ($folder->stays as $stay) {
            if ($stay->status === 'cancelled') {
                continue;
            }
            $kind = $stay->booked_by === 'client' || $stay->status === 'booked_by_client' ? 'client' : 'stay';
            $add($stay->check_in, ['type' => 'stay_in', 'sort' => 1, 'label' => __('Arrivée à l’hôtel : :h', ['h' => $stay->hotel_name]), 'sub' => trim(($stay->city ?? '') . ' · ' . (FolderStayLabels::status($stay->status)), ' ·'), 'state' => $kind]);
            $add($stay->check_out, ['type' => 'stay_out', 'sort' => 0, 'label' => __('Départ de l’hôtel : :h', ['h' => $stay->hotel_name]), 'sub' => null, 'state' => $kind]);
        }

        foreach ($folder->flightSegments as $flight) {
            $route = trim(($flight->departure_airport ?? '?') . ' → ' . ($flight->arrival_airport ?? '?'));
            $label = trim(($flight->airline ? $flight->airline . ' ' : '') . ($flight->flight_number ?? '')) ?: __('Vol');
            $add($flight->departure_local, ['type' => 'flight', 'sort' => 2, 'label' => '✈️ ' . $label . ' · ' . $route, 'sub' => __('Départ :t (heure locale)', ['t' => $flight->departure_local?->format('H:i')]), 'state' => 'info']);
            if ($flight->arrival_local && ($flight->departure_local?->format('Y-m-d') !== $flight->arrival_local->format('Y-m-d'))) {
                $add($flight->arrival_local, ['type' => 'flight', 'sort' => 2, 'label' => '✈️ ' . $label . ' · ' . __('arrivée :a', ['a' => $flight->arrival_airport ?? '?']), 'sub' => __('Arrivée :t (heure locale)', ['t' => $flight->arrival_local->format('H:i')]), 'state' => 'info']);
            } elseif ($flight->arrival_local) {
                $days[$flight->arrival_local->format('Y-m-d')]['entries'][count($days[$flight->arrival_local->format('Y-m-d')]['entries']) - 1]['sub'] .= ' · ' . __('arrivée :t', ['t' => $flight->arrival_local->format('H:i')]);
            }
        }

        foreach ($planned as $item) {
            $add($item->service_date, self::itemEntry($item, 'planned'));
        }
        foreach ($ordered as $item) {
            $add($item->service_date, self::itemEntry($item, 'ordered'));
        }

        foreach ($days as &$day) {
            usort($day['entries'], fn ($a, $b) => [$a['sort'], $a['label']] <=> [$b['sort'], $b['label']]);
        }
        unset($day);
        ksort($days);

        return ['days' => $days, 'alerts' => self::alerts($folder, $days, $planned, $ordered)];
    }

    private static function itemEntry($item, string $state): array
    {
        $time = $item->meeting_time ? $item->meeting_time . ' ' : '';
        $where = $item->meeting_point ? ' · ' . $item->meeting_point : '';
        $status = $state === 'planned' ? __('En préparation') : ($item->itemStatus?->name ?? __('Commandée'));

        return [
            'type' => 'item', 'sort' => 5,
            'label' => $time . ($item->product?->name ?? $item->title ?? __('Prestation')),
            'sub' => $status . $where,
            'state' => $state, 'note' => $item->ticketing_notes ? true : false,
        ];
    }

    /** @return array<int, string> */
    private static function alerts(Folder $folder, array $days, Collection $planned, Collection $ordered): array
    {
        $alerts = [];

        // Nuits non couvertes entre le début et la fin du séjour.
        if ($folder->start_date && $folder->end_date) {
            $covered = [];
            foreach ($folder->stays->where('status', '!=', 'cancelled') as $stay) {
                if ($stay->check_in && $stay->check_out) {
                    for ($d = $stay->check_in->copy(); $d->lt($stay->check_out); $d->addDay()) {
                        $covered[$d->format('Y-m-d')] = ($covered[$d->format('Y-m-d')] ?? 0) + 1;
                    }
                }
            }
            $missing = [];
            for ($d = $folder->start_date->copy(); $d->lt($folder->end_date); $d->addDay()) {
                if (empty($covered[$d->format('Y-m-d')])) {
                    $missing[] = $d->format('d/m');
                }
            }
            if ($missing && $folder->stays->isNotEmpty()) {
                $alerts[] = __('Nuit(s) sans hébergement : :d', ['d' => implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? '…' : '')]);
            } elseif ($missing) {
                $alerts[] = __('Aucun hébergement renseigné.');
            }
            $overlap = array_keys(array_filter($covered, fn ($n) => $n > 1));
            if ($overlap) {
                $alerts[] = __('Hébergements qui se chevauchent : :d', ['d' => implode(', ', array_map(fn ($k) => Carbon::parse($k)->format('d/m'), array_slice($overlap, 0, 6)))]);
            }
        }

        if ($folder->flightSegments->isEmpty()) {
            $alerts[] = __('Aucun vol renseigné.');
        }

        foreach ($folder->stays as $stay) {
            if ($stay->needsTakadaBooking()) {
                $alerts[] = __('Hôtel à réserver ou en attente : :h', ['h' => $stay->hotel_name]);
            }
        }

        $timed = $planned->concat($ordered)->filter(fn ($i) => in_array($i->product?->category?->name, self::TIME_EXPECTED, true) && blank($i->meeting_time));
        if ($timed->isNotEmpty()) {
            $alerts[] = __(':n prestation(s) de guidage ou de transfert sans horaire de rendez-vous.', ['n' => $timed->count()]);
        }

        foreach ($days as $key => $day) {
            $transports = collect($day['entries'])->filter(fn ($e) => $e['type'] === 'item')->filter(fn ($e) => str_contains($e['label'], 'Shinkansen') || str_contains($e['label'], 'Train') || str_contains($e['label'], 'Bus'))->count();
            if ($transports > 2) {
                $alerts[] = __('Plus de deux transports le :d : à vérifier.', ['d' => $day['date']->format('d/m')]);
            }
        }

        return $alerts;
    }
}
