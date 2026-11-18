<?php

namespace App\Support;

use App\Models\TransportFare;

/**
 * Détermine le calendrier de saisons JR d'un train (« A », « B », « K » ou null = pas de saison),
 * d'après les mots-clés de config/transport_fares.php.
 */
class TransportFareCalendar
{
    /** Calendriers où l'on peut saisir des périodes (K n'a pas de périodes propres : il suit A sans saison basse). */
    public const CALENDARS = ['A', 'B'];

    public static function forTrain(?string $trainName, string $pricingMode): ?string
    {
        if ($pricingMode !== TransportFare::MODE_SEASONAL) {
            return null;
        }

        $match = self::match($trainName);

        return $match !== null ? $match['calendar'] : (self::config()['default_calendar'] ?? 'A');
    }

    /** True si le nom du train contient un mot-clé connu (donc n'a pas pris le calendrier par défaut). */
    public static function hasKeyword(?string $trainName): bool
    {
        return self::match($trainName) !== null;
    }

    /** @return array{calendar: ?string}|null */
    private static function match(?string $trainName): ?array
    {
        $name = mb_strtolower(trim((string) $trainName));
        if ($name === '') {
            return null;
        }
        foreach (self::config()['groups'] ?? [] as $group) {
            foreach ($group['keywords'] as $keyword) {
                if (preg_match('/(?<![a-z0-9])' . preg_quote(mb_strtolower($keyword), '/') . '(?![a-z0-9])/u', $name)) {
                    return ['calendar' => $group['calendar']];
                }
            }
        }

        return null;
    }

    /** Config lue aussi à la volée : un `config:cache` antérieur au fichier ne doit pas la rendre introuvable (migration). */
    private static function config(): array
    {
        return config('transport_fares') ?: (is_file($f = config_path('transport_fares.php')) ? require $f : []);
    }
}
