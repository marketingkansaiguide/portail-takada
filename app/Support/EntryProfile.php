<?php

namespace App\Support;

use App\Models\Product;

/**
 * Profil de saisie d'une prestation dans l'espace commercial : détermine le bloc contextuel du panneau.
 * Déduit de la catégorie du produit ; `products.entry_profile` permet de le surcharger produit par produit.
 */
final class EntryProfile
{
    public const VISIT = 'visit';
    public const AIRPORT_WELCOME = 'airport_welcome';
    public const TRANSFER = 'transfer';
    public const TRAIN_BUS = 'train_bus';
    public const RENTAL = 'rental';
    public const OTHER = 'other';

    public const ALL = [self::VISIT, self::AIRPORT_WELCOME, self::TRANSFER, self::TRAIN_BUS, self::RENTAL, self::OTHER];

    public static function for(?Product $product): string
    {
        if (! $product) {
            return self::OTHER;
        }
        if (in_array($product->entry_profile, self::ALL, true)) {
            return $product->entry_profile;
        }

        $name = mb_strtolower((string) $product->name);
        $category = mb_strtolower((string) $product->category?->name);

        return match (true) {
            str_contains($name, 'accueil aéroport') || str_contains($name, 'accueil aeroport') => self::AIRPORT_WELCOME,
            str_contains($category, 'transfert') => self::TRANSFER,
            $product->product_type === 'transport' || str_contains($category, 'transport') || str_contains($name, 'train') || str_contains($name, 'bus') => self::TRAIN_BUS,
            str_contains($name, 'pass') || str_contains($name, 'location') || str_contains($name, 'wifi') || str_contains($name, 'carte') => self::RENTAL,
            str_contains($category, 'guidage') || str_contains($category, 'activit') => self::VISIT,
            default => self::OTHER,
        };
    }

    /** Champs affichés dans le bloc contextuel, par profil. */
    public static function fields(string $profile): array
    {
        return match ($profile) {
            self::VISIT => ['meeting_time', 'meeting_point'],
            self::AIRPORT_WELCOME => ['flight', 'meeting_time', 'meeting_point'],
            self::TRANSFER => ['meeting_time', 'route_from', 'route_to'],
            self::TRAIN_BUS => ['route_from', 'route_to', 'meeting_time', 'end_time'],
            self::RENTAL => ['end_date', 'meeting_point'],
            default => ['meeting_time', 'meeting_point'],
        };
    }

    /** « Ticket de train / bus » + Kanazawa, Takayama => « Train Kanazawa → Takayama ». */
    public static function routeLabel(string $name, ?string $from, ?string $to): string
    {
        if (blank($from) || blank($to)) {
            return $name;
        }
        $base = \Illuminate\Support\Str::before($name, ' / ');
        $base = \Illuminate\Support\Str::ucfirst(trim((string) preg_replace('/^ticket\s+(de\s+|d[’\']\s*)?/iu', '', $base)));

        return $base . ' ' . $from . ' → ' . $to;
    }
}
