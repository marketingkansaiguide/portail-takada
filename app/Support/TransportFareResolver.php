<?php

namespace App\Support;

use App\Models\TransportFare;
use App\Models\TransportFareSeason;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cherche le prix indicatif d'un trajet de billet train / bus dans la grille tarifaire.
 *
 * Règles :
 * - trajet trouvé dans les deux sens (sauf si la ligne est à sens unique) ;
 * - classe = option du produit choisie par l'agence ;
 * - train : si l'agence a écrit un nom de train connu (« Hikari 502 »), on prend sa ligne ;
 *   sinon on prend la ligne « tous trains », ou à défaut le train le plus cher du trajet (référence) ;
 * - saison déduite de la date (calendrier des saisons) ; case vide = pas de prix (reste sur devis) ;
 * - prix indicatif : adultes au tarif adulte, enfants au tarif enfant, gratuités JR (voir passengers()).
 */
class TransportFareResolver
{
    /** @var array<string, Collection> */
    private array $cache = [];

    /**
     * @param  array<string, mixed>  $route  un élément de custom_values.transport_routes
     * @return array{unit_price:int, child_price:int, pax:int, adults:int, children:int, free:int, total:int, season:string, is_max_reference:bool, train_name:?string, fare_id:int}|null
     */
    public function forRoute(array $route): ?array
    {
        $from = $route['departure_station'] ?? null;
        $to = $route['arrival_station'] ?? null;
        $optionId = $route['option_id'] ?? null;
        $date = $route['departure_date'] ?? null;
        if (blank($from) || blank($to) || blank($optionId) || blank($date)) {
            return null;
        }

        try {
            $day = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }

        $fares = $this->routeFares((string) $from, (string) $to, $day)
            ->where('product_option_id', (int) $optionId);
        if ($fares->isEmpty()) {
            return null;
        }

        $seasons = [];

        // 1. Train nommé par l'agence et présent dans la grille.
        $named = $this->namedTrainFares($fares, $route);
        // 2. Sinon la ligne « tous trains ».
        $generic = $fares->whereNull('train_name');

        $pool = $named->isNotEmpty() ? $named : ($generic->isNotEmpty() ? $generic : $fares);
        // Train non précisé : le train le plus cher du trajet sert de référence (décision Takada).
        $priced = $pool
            ->map(function (TransportFare $f) use ($day, &$seasons) {
                // Chaque tarif suit son calendrier de saisons (A, B, K ou aucun).
                $season = $seasons[$f->season_calendar ?? ''] ??= TransportFareSeason::seasonFor($day, $f->season_calendar);

                return ['fare' => $f, 'season' => $season, 'price' => $f->priceForSeason($season)];
            })
            ->filter(fn ($x) => $x['price'] !== null && $x['price'] > 0)
            ->sortByDesc('price')
            ->values();
        if ($priced->isEmpty()) {
            return null;
        }

        $best = $priced->first();
        $isMaxReference = $named->isEmpty() && $generic->isEmpty() && $priced->pluck('price')->unique()->count() > 1;
        $adultPrice = $best['price'];
        $childPrice = $best['fare']->childPriceFor($adultPrice);
        $pax = self::passengers($route);

        return [
            'unit_price' => $adultPrice,
            'child_price' => $childPrice,
            'pax' => $pax['total'],
            'adults' => $pax['adults'],
            'children' => $pax['children_paying'],
            'free' => $pax['free'],
            'total' => $adultPrice * $pax['adults'] + $childPrice * $pax['children_paying'],
            'season' => $best['season'],
            'is_max_reference' => $isMaxReference,
            'train_name' => $best['fare']->train_name,
            'fare_id' => $best['fare']->id,
        ];
    }

    /**
     * Répartition tarifaire des passagers d'un trajet (règles JR) :
     * - pax_count = nombre total de voyageurs ;
     * - children_count (6-11 ans, ou 1-5 ans occupant sa propre place réservée) : demi-tarif ;
     * - young_children_count (1-5 ans sans place) : gratuits, au plus 2 par voyageur payant, les suivants au tarif enfant ;
     * - infants_count (moins d'1 an) : gratuits ;
     * - le reste = adultes (12 ans et plus).
     *
     * @return array{total:int, adults:int, children_paying:int, free:int}
     */
    public static function passengers(array $route): array
    {
        $total = max(1, (int) ($route['pax_count'] ?? 1));
        $children = max(0, (int) ($route['children_count'] ?? 0));
        $young = max(0, (int) ($route['young_children_count'] ?? 0));
        $infants = max(0, (int) ($route['infants_count'] ?? 0));

        // Les catégories ne peuvent pas dépasser le total saisi.
        $infants = min($infants, $total);
        $young = min($young, $total - $infants);
        $children = min($children, $total - $infants - $young);
        $adults = $total - $infants - $young - $children;

        // Un jeune enfant seul (sans voyageur payant) paie ; au-delà de 2 par payant aussi.
        $payers = $adults + $children;
        $youngFree = min($young, 2 * $payers);
        $youngPaying = $young - $youngFree;
        if ($payers === 0 && $young > 0) {
            $youngPaying = $young;
            $youngFree = 0;
        }

        return [
            'total' => $total,
            'adults' => $adults,
            'children_paying' => $children + $youngPaying,
            'free' => $infants + $youngFree,
        ];
    }

    /**
     * Prix de chaque trajet + total des trajets chiffrés.
     *
     * @param  array<int|string, array<string, mixed>>  $routes
     * @return array{routes: array<int|string, array|null>, total: int, priced: int, count: int, has_max_reference: bool}
     */
    public function forRoutes(array $routes): array
    {
        $out = ['routes' => [], 'total' => 0, 'priced' => 0, 'count' => 0, 'has_max_reference' => false];
        foreach ($routes as $key => $route) {
            if (! is_array($route)) {
                continue;
            }
            $out['count']++;
            $fare = $this->forRoute($route);
            $out['routes'][$key] = $fare;
            if ($fare) {
                $out['priced']++;
                $out['total'] += $fare['total'];
                $out['has_max_reference'] = $out['has_max_reference'] || $fare['is_max_reference'];
            }
        }

        return $out;
    }

    /**
     * Copie le prix indicatif dans chaque trajet (clé « fare_estimate ») au moment de la commande,
     * pour que l'équipe voie ce que l'agence a vu. Trajet sans prix : clé retirée.
     *
     * @param  array<int|string, array<string, mixed>>  $routes
     * @return array<int|string, array<string, mixed>>
     */
    public function withEstimates(array $routes): array
    {
        foreach ($routes as $key => $route) {
            if (! is_array($route)) {
                continue;
            }
            unset($routes[$key]['fare_estimate']);
            if ($fare = $this->forRoute($route)) {
                $routes[$key]['fare_estimate'] = [
                    'total' => $fare['total'],
                    'unit_price' => $fare['unit_price'],
                    'child_price' => $fare['child_price'],
                    'adults' => $fare['adults'],
                    'children' => $fare['children'],
                    'free' => $fare['free'],
                    'season' => $fare['season'],
                    'train_name' => $fare['train_name'],
                    'is_max_reference' => $fare['is_max_reference'],
                    'computed_at' => now()->toDateTimeString(),
                ];
            }
        }

        return $routes;
    }

    /** Libellé court d'un prix indicatif, ex. « 35 420 ¥ (2 ad. × 14 170 ¥ + 1 enf. × 7 080 ¥, 1 gratuit) ». */
    public static function describe(array $fare): string
    {
        $fmt = fn ($n) => number_format((int) $n, 0, '.', ' ') . ' ¥';
        $parts = [];
        if (($fare['adults'] ?? 0) > 0) {
            $parts[] = $fare['adults'] . ' ' . __('ad.') . ' × ' . $fmt($fare['unit_price']);
        }
        if (($fare['children'] ?? 0) > 0) {
            $parts[] = $fare['children'] . ' ' . __('enf.') . ' × ' . $fmt($fare['child_price']);
        }
        $detail = implode(' + ', $parts);
        if (($fare['free'] ?? 0) > 0) {
            $detail .= ', ' . $fare['free'] . ' ' . ($fare['free'] > 1 ? __('gratuits') : __('gratuit'));
        }

        return $fmt($fare['total']) . ($detail !== '' ? " ({$detail})" : '');
    }

    public static function seasonLabel(string $season): string
    {
        return __(TransportFareSeason::SEASONS[$season] ?? $season);
    }

    /**
     * Classes (product_option_id) qui ont au moins un tarif sur ce trajet, ou null si le trajet
     * n'existe pas dans la grille (rien n'est alors grisé, le prix reste « Sur devis »).
     * Si le train saisi correspond à une ligne, seules les lignes de ce train et les lignes « tous trains » comptent.
     *
     * @param  array<string, mixed>  $route
     * @return array<int, int>|null
     */
    public function availableOptions(array $route): ?array
    {
        $from = $route['departure_station'] ?? null;
        $to = $route['arrival_station'] ?? null;
        if (blank($from) || blank($to)) {
            return null;
        }

        $day = null;
        if (filled($route['departure_date'] ?? null)) {
            try {
                $day = Carbon::parse($route['departure_date'])->toDateString();
            } catch (\Throwable) {
                $day = null;
            }
        }

        $fares = $this->routeFares((string) $from, (string) $to, $day);
        if ($fares->isEmpty()) {
            return null;
        }

        $named = $this->namedTrainFares($fares, $route);
        $pool = $named->isNotEmpty() ? $named->merge($fares->whereNull('train_name')) : $fares;

        return $pool->pluck('product_option_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Classe à retenir si celle du trajet n'est pas disponible : réservée (24), sinon la première
     * disponible dans l'ordre 24, 23, 25, 26. Renvoie null si aucun changement n'est nécessaire.
     *
     * @param  array<string, mixed>  $route
     */
    public function fallbackOption(array $route): ?int
    {
        $available = $this->availableOptions($route);
        if ($available === null || $available === []) {
            return null;
        }
        $current = $route['option_id'] ?? null;
        if (blank($current) || in_array((int) $current, $available, true)) {
            return null;
        }
        foreach ([24, 23, 25, 26] as $id) {
            if (in_array($id, $available, true)) {
                return $id;
            }
        }

        return $available[0];
    }

    /**
     * Applique la bascule de classe à une liste de trajets.
     * Pose la clé « option_switch_note » sur un trajet basculé, la retire sinon.
     *
     * @param  array<int|string, array<string, mixed>>  $routes
     * @return array<int|string, array<string, mixed>>
     */
    public function adjustRoutes(array $routes, ?array $names = null): array
    {
        foreach ($routes as $key => $route) {
            if (! is_array($route)) {
                continue;
            }
            unset($routes[$key]['option_switch_note']);
            if (($new = $this->fallbackOption($route)) !== null) {
                $routes[$key]['option_id'] = $new;
                $label = $names[$new] ?? null;
                $routes[$key]['option_switch_note'] = $new === 24 || ! $label
                    ? __('Classe indisponible sur ce trajet, passée en place réservée.')
                    : __('Classe indisponible sur ce trajet, passée en :classe.', ['classe' => mb_strtolower($label)]);
            }
        }

        return $routes;
    }

    /** Lignes actives du trajet (toutes classes), dans le sens saisi ou inverse si la ligne est bidirectionnelle. */
    private function routeFares(string $from, string $to, ?string $day = null): Collection
    {
        $a = TransportFare::stationKey($from);
        $b = TransportFare::stationKey($to);

        $all = $this->cache["{$a}|{$b}"] ??= TransportFare::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('departure_key', $a)->where('arrival_key', $b))
                ->orWhere(fn ($q) => $q->where('both_directions', true)->where('departure_key', $b)->where('arrival_key', $a)))
            ->get();

        if ($day === null) {
            return $all;
        }

        return $all->filter(fn (TransportFare $f) => (! $f->valid_from || $f->valid_from->toDateString() <= $day)
            && (! $f->valid_to || $f->valid_to->toDateString() >= $day))->values();
    }

    /** Lignes dont le nom de train figure dans le n° de train / l'horaire saisi par l'agence. */
    private function namedTrainFares(Collection $fares, array $route): Collection
    {
        $trainText = mb_strtolower(trim(($route['train_number'] ?? '') . ' ' . ($route['departure_time'] ?? '')));

        return $fares->filter(fn (TransportFare $f) => $f->train_name !== null
            && $trainText !== ''
            && preg_match('/(?<![a-z0-9])' . preg_quote(mb_strtolower($f->train_name), '/') . '(?![a-z])/u', $trainText));
    }
}