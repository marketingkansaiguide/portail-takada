<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recherche de prestations pour l'espace commercial. Toujours côté serveur : le catalogue n'est jamais
 * chargé en entier. Ne réutilise pas la requête B2B (aucun filtre de groupe client côté interne).
 */
final class CatalogueSearch
{
    public const MIN_CHARS = 2;
    public const LIMIT = 15;
    public const FREQUENT_LIMIT = 8;
    public const FREQUENT_DAYS = 90;

    /** @return array<int, string> id => libellé HTML sur deux lignes (nom ; catégorie · ville), échappé */
    public static function search(string $term, int $limit = self::LIMIT, ?int $alsoId = null): array
    {
        $term = trim($term);
        if (mb_strlen($term) < self::MIN_CHARS) {
            return [];
        }
        $like = '%' . addcslashes($term, '%_\\') . '%';

        $products = Product::query()
            ->select('products.id', 'products.name', 'products.guide_city', 'products.category_id')
            ->with('category:id,name')
            ->where(function ($q) use ($alsoId) {
                $q->where('products.is_public', true);
                if ($alsoId) {
                    $q->orWhere('products.id', $alsoId);
                }
            })
            ->where(function ($q) use ($like) {
                $q->where('products.name', 'like', $like)
                    ->orWhere('products.guide_city', 'like', $like)
                    ->orWhere('products.departure_station', 'like', $like)
                    ->orWhere('products.arrival_station', 'like', $like)
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like))
                    ->orWhereExists(fn ($e) => $e->select(DB::raw(1))->from('product_options')
                        ->whereColumn('product_options.product_id', 'products.id')->where('product_options.name', 'like', $like));
            })
            ->orderByRaw('CASE WHEN products.name LIKE ? THEN 0 ELSE 1 END', [$like])
            ->orderBy('products.name')
            ->limit($limit)
            ->get();

        return $products->mapWithKeys(fn (Product $p) => [$p->id => self::html($p)])->all();
    }

    /** Prestations les plus préparées par l'utilisateur sur 90 jours (requête simple sur folder_planned_items). */
    public static function frequent(?int $userId, int $limit = self::FREQUENT_LIMIT): array
    {
        if (! $userId || ! Schema::hasTable('folder_planned_items')) {
            return [];
        }
        $ids = DB::table('folder_planned_items')
            ->where('created_by_user_id', $userId)
            ->where('created_at', '>=', now()->subDays(self::FREQUENT_DAYS))
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck('product_id');
        if ($ids->isEmpty()) {
            return [];
        }
        $products = Product::query()->with('category:id,name')->where('is_public', true)->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->filter(fn ($id) => $products->has($id))->mapWithKeys(fn ($id) => [$id => self::html($products[$id])])->all();
    }

    private static function html(Product $p): string
    {
        $second = trim(implode(' · ', array_filter([$p->category?->name, $p->guide_city])));

        return '<span style="display:block">' . e($p->name) . '</span>'
            . ($second !== '' ? '<span style="display:block;font-size:.75rem;color:#6b7280">' . e($second) . '</span>' : '');
    }
}
