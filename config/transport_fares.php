<?php

/**
 * Calendriers de saisons des tarifs JR (billets train).
 *
 * - « A » : Tokaido, Sanyo, Kyushu (sauf K), + express JR Central, JR West, JR Shikoku et trains multi-compagnies.
 * - « B » : JR East (Tohoku, Joetsu, Hokuriku, Yamagata, Akita, Hokkaido Shinkansen), Thunderbird, Shirasagi.
 * - « K » : express de JR Kyushu en ligne classique et Nishi-Kyushu Shinkansen = calendrier A sans saison basse.
 * - aucun (null) : pas de saison, le prix « normal » s'applique toute l'année.
 *
 * Les mots-clés sont comparés en minuscules, sur le mot entier, au nom du train de la grille.
 * Ordre de priorité : B, aucun, Hokkaido, K, A. Mode « prix unique » → toujours aucun.
 * Seasonal sans correspondance (ou train vide, « tous trains ») : `default_calendar`.
 */
return [
    'default_calendar' => 'A',

    // Ordre = priorité. Valeur de calendrier : 'A', 'B', 'K' ou null (aucun).
    'groups' => [
        ['calendar' => 'B', 'keywords' => [
            'hayabusa', 'hayate', 'yamabiko', 'nasuno', 'tsubasa', 'komachi', 'toki', 'tanigawa',
            'kagayaki', 'hakutaka', 'tsurugi', 'asama', 'thunderbird', 'shirasagi',
        ]],

        // Pas de saison : lignes privées, prix fixe toute l'année.
        ['calendar' => null, 'keywords' => [
            'azusa', 'kaiji', 'hitachi', 'tokiwa', 'odoriko', 'saphir', 'fuji excursion', 'kintetsu',
            'hinotori', 'aoniyoshi', 'shimakaze', 'urban liner', 'tobu', 'spacia', 'revaty', 'kegon',
            'kinu', 'odakyu', 'romancecar', 'nankai', 'rapit', 'meitetsu', 'skyliner', 'keisei',
            // Ajouts « évidents » (lignes privées) :
            'seibu', 'laview', 'red arrow', 'fujikyu', 'hankyu', 'hanshin', 'keihan', 'tokyu',
        ]],

        // Express de JR Hokkaido (ligne classique) : aucune saison (confirmé).
        ['calendar' => null, 'keywords' => [
            'hokuto', 'ozora', 'kamui', 'lilac', 'okhotsk', 'taisetsu', 'suzuran', 'sarobetsu', 'tokachi',
        ]],

        // JR Kyushu en ligne classique + Nishi-Kyushu Shinkansen : calendrier A sans saison basse.
        ['calendar' => 'K', 'keywords' => [
            'kamome', 'relay kamome', 'sonic', 'nichirin', 'kirishima', 'midori', 'huis ten bosch', 'yufu',
            'aso', 'ibusuki', 'kyushu oudan', 'yufuin no mori', 'kawasemi', 'yamasemi', '36+3', 'aru ressha',
        ]],

        ['calendar' => 'A', 'keywords' => [
            'nozomi', 'hikari', 'kodama', 'mizuho', 'sakura', 'tsubame', 'haruka', 'kuroshio',
            'hida', 'nanki', 'hashidate', 'kounotori', 'hakuto', 'yakumo', 'shiokaze', 'nanpu',
            'ishizuchi', 'uwakai', 'ashizuri',
            // Trains multi-compagnies :
            'shinano', 'sunrise',
        ]],
    ],
];
