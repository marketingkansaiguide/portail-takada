<?php

namespace App\Support;

final class PermissionCatalog
{
    private const CRUD = [
        'viewAny' => 'Voir la liste',
        'view' => 'Voir le détail',
        'create' => 'Créer',
        'update' => 'Modifier',
        'delete' => 'Supprimer',
    ];

    /** @return array<string, array<string, string>> groupe => [permission => libellé] */
    public static function groups(): array
    {
        $crud = fn (string $key, string $label): array => collect(self::CRUD)
            ->mapWithKeys(fn ($action, $a) => ["{$key}.{$a}" => "{$action} : {$label}"])
            ->all();

        return [
            'Dossiers de voyage' => $crud('folder', 'dossiers') + [
                'folder.segment.fit' => 'Périmètre : dossiers FIT',
                'folder.segment.groupe' => 'Périmètre : dossiers Groupe',
                'folder.segment.billetterie' => 'Périmètre : dossiers Billetterie (agences partenaires)',
                'folder.segment.all' => 'Périmètre : tous les dossiers (y compris non classés)',
            ],
            'Catalogue produits' => $crud('product', 'produits') + $crud('category', 'catégories'),
            'Agences' => $crud('agency', 'agences'),
            'Utilisateurs & système' => $crud('user', 'utilisateurs') + $crud('setting', 'paramètres globaux') + $crud('client_group', 'groupes clients & prix'),
            'Référentiels' => $crud('supplier', 'fournisseurs') + $crud('hotel', 'hébergements') + $crud('item_status', 'statuts de prestation'),
            'Gares & stations' => $crud('train_station', 'gares') + $crud('bus_station', 'stations de bus'),
            'Opérations & rapports' => [
                'dashboard.view' => 'Accéder au tableau de bord',
                'operations.checkins' => 'Check-ins',
                'operations.train_tickets' => 'Billets de train',
                'operations.iwa_orders' => 'Commandes IWA',
                'operations.ic_cards' => 'Cartes IC',
                'operations.seven_tickets' => 'Commandes Seven Tickets',
                'operations.statistics' => 'Statistiques financières (Business Intelligence)',
            ],
            'SEO' => ['seo.dashboard' => 'Tableau de bord SEO'] + $crud('seo_site', 'sites suivis'),
        ];
    }
}
