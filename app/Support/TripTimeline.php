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
     * @return array{days: array<string, array{date: Carbon, entries: array<int, array<string, mixed>>}>, alerts: array<int, array<string, mixed>>}
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
        $time = $item->meeting_time ? $item->meeting_time . ($item->end_time ? ' → ' . $item->end_time : '') . ' ' : '';
        $where = $item->meeting_point ? ' · ' . $item->meeting_point : '';
        $status = $state === 'planned' ? __('En préparation') : ($item->itemStatus?->name ?? __('Commandée'));

        return [
            'type' => 'item', 'sort' => 5,
            'label' => $time . EntryProfile::itemLabel((string) ($item->product?->name ?? $item->title ?? __('Prestation')), (array) $item->custom_values, $item->route_from, $item->route_to),
            'sub' => $status . $where,
            'state' => $state, 'note' => $item->ticketing_notes ? true : false,
        ];
    }

    /**
     * Alertes structurées : type, cible (élément à ouvrir), libellé, plage éventuelle.
     *
     * @return array<int, array{type: string, target: array<string, mixed>|null, label: string, range: array<string, mixed>|null}>
     */
    private static function alerts(Folder $folder, array $days, Collection $planned, Collection $ordered): array
    {
        $alerts = [];
        $add = function (string $type, string $label, ?array $target = null, ?array $range = null) use (&$alerts): void {
            $alerts[] = ['type' => $type, 'target' => $target, 'label' => $label, 'range' => $range];
        };

        $stays = $folder->stays->where('status', '!=', 'cancelled')->values();

        // 1. Séjours incomplets : l'alerte porte sur le séjour, jamais sur ses nuits.
        $dated = $stays->filter(fn ($s) => $s->check_in && $s->check_out);
        foreach ($stays as $stay) {
            if ($stay->check_in && $stay->check_out) {
                continue;
            }
            $target = ['kind' => 'stay', 'id' => $stay->id, 'tab' => 'stays'];
            $name = $stay->hotel_name ?: __('hôtel à préciser');
            if ($stay->check_in && ! $stay->check_out) {
                $add('stay_missing_checkout', __('Hôtel sans date de départ : :h (:d)', ['h' => $name, 'd' => $stay->check_in->format('d/m')]), $target);
            } elseif (! $stay->check_in && $stay->check_out) {
                $add('stay_missing_checkin', __('Hôtel sans date d’arrivée : :h (départ :d)', ['h' => $name, 'd' => $stay->check_out->format('d/m')]), $target);
            } else {
                $add('stay_missing_dates', __('Hôtel sans dates : :h', ['h' => $name]), $target);
            }
        }

        // 2. Nuits couvertes par aucun séjour daté. Les nuits d'un séjour dont le départ est inconnu
        //    (de son arrivée jusqu'au séjour daté suivant) sont en attente de dates, pas manquantes.
        if ($folder->start_date && $folder->end_date) {
            $covered = [];
            foreach ($dated as $stay) {
                for ($d = $stay->check_in->copy(); $d->lt($stay->check_out); $d->addDay()) {
                    $covered[$d->format('Y-m-d')] = ($covered[$d->format('Y-m-d')] ?? 0) + 1;
                }
            }
            $pending = [];
            foreach ($stays->filter(fn ($s) => $s->check_in && ! $s->check_out) as $stay) {
                $next = $dated->filter(fn ($s) => $s->check_in->gt($stay->check_in))->min(fn ($s) => $s->check_in->format('Y-m-d'));
                $limit = $next ? Carbon::parse($next) : $folder->end_date->copy();
                for ($d = $stay->check_in->copy(); $d->lt($limit); $d->addDay()) {
                    $pending[$d->format('Y-m-d')] = true;
                }
            }

            $missing = [];
            for ($d = $folder->start_date->copy(); $d->lt($folder->end_date); $d->addDay()) {
                $k = $d->format('Y-m-d');
                if (empty($covered[$k]) && empty($pending[$k])) {
                    $missing[] = $d->copy();
                }
            }
            if ($missing && $stays->isEmpty()) {
                $add('no_lodging', __('Aucun hébergement renseigné.'), ['kind' => 'stay', 'id' => null, 'tab' => 'stays']);
            } else {
                foreach (self::groupNights($missing) as [$from, $to, $n]) {
                    $add(
                        'nights_missing',
                        __('Nuits sans hébergement : :from → :to, :n nuit(s)', ['from' => $from->format('d/m'), 'to' => $to->format('d/m'), 'n' => $n]),
                        ['kind' => 'stay', 'id' => null, 'tab' => 'stays', 'check_in' => $from->format('Y-m-d'), 'nights' => $n],
                        ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'nights' => $n],
                    );
                }
            }

            $overlap = array_keys(array_filter($covered, fn ($n) => $n > 1));
            if ($overlap) {
                $add('stays_overlap', __('Hébergements qui se chevauchent : :d', ['d' => implode(', ', array_map(fn ($k) => Carbon::parse($k)->format('d/m'), array_slice($overlap, 0, 6)))]), ['kind' => 'stay', 'id' => null, 'tab' => 'stays']);
            }
        }

        if ($folder->flightSegments->isEmpty()) {
            $add('no_flight', __('Aucun vol renseigné.'), ['kind' => 'flight', 'id' => null, 'tab' => 'flights']);
        }

        // 3. Un séjour réservé par le client ne produit jamais d'action « à commander » (needsTakadaBooking).
        foreach ($stays as $stay) {
            if ($stay->needsTakadaBooking()) {
                $add('stay_to_book', __('Hôtel à réserver ou en attente : :h', ['h' => $stay->hotel_name]), ['kind' => 'stay', 'id' => $stay->id, 'tab' => 'stays']);
            }
        }

        $timed = $planned->concat($ordered)->filter(fn ($i) => in_array($i->product?->category?->name, self::TIME_EXPECTED, true) && blank($i->meeting_time));
        if ($timed->isNotEmpty()) {
            $add('items_without_time', __(':n prestation(s) de guidage ou de transfert sans horaire de rendez-vous.', ['n' => $timed->count()]), ['kind' => 'item', 'id' => $timed->first()->id, 'tab' => 'items']);
        }

        foreach ($days as $key => $day) {
            $transports = collect($day['entries'])->filter(fn ($e) => $e['type'] === 'item')->filter(fn ($e) => str_contains($e['label'], 'Shinkansen') || str_contains($e['label'], 'Train') || str_contains($e['label'], 'Bus'))->count();
            if ($transports > 2) {
                $add('many_transports', __('Plus de deux transports le :d : à vérifier.', ['d' => $day['date']->format('d/m')]), ['kind' => 'day', 'id' => $key, 'tab' => 'timeline']);
            }
        }

        return $alerts;
    }

    /**
     * Regroupe des nuits consécutives en plages.
     *
     * @param  array<int, Carbon>  $nights
     * @return array<int, array{0: Carbon, 1: Carbon, 2: int}>
     */
    private static function groupNights(array $nights): array
    {
        $ranges = [];
        foreach ($nights as $night) {
            $last = count($ranges) - 1;
            if ($last >= 0 && $ranges[$last][1]->copy()->addDay()->isSameDay($night)) {
                $ranges[$last][1] = $night;
                $ranges[$last][2]++;
            } else {
                $ranges[] = [$night, $night, 1];
            }
        }

        return $ranges;
    }
}
