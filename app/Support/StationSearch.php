<?php

namespace App\Support;

use App\Models\BusStation;
use App\Models\TrainStation;

/**
 * Recherche de gares (trains et bus) pour l'autocomplétion des trajets.
 * Classement : correspondance exacte (« tokyo » ou « tokyo station »), puis nom commençant par la saisie,
 * puis nom contenant la saisie, puis ville / préfecture / adresse ; à rang égal, la gare la plus importante d'abord.
 */
class StationSearch
{
    /** @return array<int, array{name:string, label:string}> */
    public static function search(?string $term, int $limit = 15): array
    {
        $term = trim((string) $term);
        $lower = mb_strtolower($term);
        $has = $term !== '';
        $like = '%' . addcslashes($term, '\%_') . '%';
        $prefix = addcslashes($term, '\%_') . '%';

        // Rang SQL : 0 exact, 1 préfixe, 2 contient dans le nom, 3 autre champ (ville, préfecture, adresse).
        $rank = function ($query) use ($lower, $prefix, $like) {
            $query->orderByRaw(
                'CASE WHEN LOWER(name_en) IN (?, ?) OR name_ja = ? THEN 0
                      WHEN name_en LIKE ? OR name_ja LIKE ? THEN 1
                      WHEN name_en LIKE ? OR name_ja LIKE ? THEN 2
                      ELSE 3 END',
                [$lower, $lower . ' station', $lower, $prefix, $prefix, $like, $like],
            );
        };
        $rankOf = function (string $nameEn, ?string $nameJa) use ($lower) {
            $en = mb_strtolower($nameEn);
            $ja = mb_strtolower((string) $nameJa);
            if ($en === $lower || $en === $lower . ' station' || $ja === $lower) {
                return 0;
            }
            if (str_starts_with($en, $lower) || ($ja !== '' && str_starts_with($ja, $lower))) {
                return 1;
            }
            if (str_contains($en, $lower) || ($ja !== '' && str_contains($ja, $lower))) {
                return 2;
            }

            return 3;
        };

        $trains = TrainStation::query()
            ->when($has, fn ($q) => $q->where(fn ($q) => $q->where('name_en', 'like', $like)->orWhere('name_ja', 'like', $like)
                ->orWhere('city', 'like', $like)->orWhere('prefecture', 'like', $like)))
            ->when($has, $rank)
            ->orderByDesc('importance_score')->orderBy('name_en')
            ->limit($limit)
            ->get(['name_en', 'name_ja', 'city', 'prefecture', 'importance_score'])
            ->map(fn ($s) => [
                'name' => $s->name_en,
                'label' => "🚆 {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : '') . ($s->city ? " - {$s->city}" : ($s->prefecture ? " - {$s->prefecture}" : '')),
                'rank' => $has ? $rankOf($s->name_en, $s->name_ja) : 0,
                'score' => $s->importance_score ?? 10,
            ]);

        $buses = BusStation::query()
            ->when($has, fn ($q) => $q->where(fn ($q) => $q->where('name_en', 'like', $like)->orWhere('name_ja', 'like', $like)
                ->orWhere('address', 'like', $like)))
            ->when($has, $rank)
            ->orderBy('name_en')
            ->limit($limit)
            ->get(['name_en', 'name_ja', 'address'])
            ->map(fn ($s) => [
                'name' => $s->name_en,
                'label' => "🚌 [Bus] {$s->name_en}" . ($s->name_ja ? " ({$s->name_ja})" : '') . ($s->address ? " - {$s->address}" : ''),
                'rank' => $has ? $rankOf($s->name_en, $s->name_ja) : 0,
                'score' => 50,
            ]);

        return $trains->concat($buses)
            ->sort(fn ($a, $b) => [$a['rank'], $b['score'], $a['name']] <=> [$b['rank'], $a['score'], $b['name']])
            ->take($limit)
            ->map(fn ($s) => ['name' => $s['name'], 'label' => $s['label']])
            ->values()->all();
    }
}
