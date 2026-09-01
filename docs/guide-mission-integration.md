# Intégration Portail Takada → Planning Guides

## Configuration

Portail Laravel :

```env
GUIDE_PLANNING_API_URL=https://planning.example/wp-json/pm/v1
GUIDE_PLANNING_API_KEY=...
GUIDE_PLANNING_CALLBACK_TOKEN=...
```

Planning Guides (`wp-config.php`) :

```php
define('PM_TICKETING_CALLBACK_URL', 'https://portail.example/api/integrations/guide-missions/status');
define('PM_TICKETING_CALLBACK_TOKEN', '...');
```

Les deux valeurs de callback token doivent être identiques. Toutes les URLs doivent utiliser HTTPS.

## Contrats API

`POST /wp-json/pm/v1/missions`, authentifié par `X-PM-Api-Key`, crée ou met à jour une mission. La paire `external_source` / `external_reference` est unique ; rejouer la même requête ne crée pas de doublon.

Le retour Planning Guides appelle `POST /api/integrations/guide-missions/status` avec un Bearer token :

```json
{
  "mission_id": 123,
  "ticketing_order_item_id": 456,
  "status": "confirmed"
}
```

`confirmed` met la prestation à « Confirmé » et `unavailable` à « Pas de disponibilité ». Le portail vérifie simultanément l’identifiant de mission et l’identifiant de prestation.

## Exploitation

Le worker Laravel doit être actif. Les appels de création/mise à jour ont quatre tentatives (`60`, `300`, puis `900` secondes). Les callbacks WordPress ont au plus trois nouvelles tentatives. Les traces sans secrets sont écrites dans `storage/logs/guide-integration-*.log`.

Après correction d’une configuration ou d’une panne prolongée : `php artisan guide-missions:retry` (ou `--item=456`).

Avant activation, renseigner pour chaque produit de catégorie Guidage sa ville et, pour chaque déclinaison concernée, ses heures de début et de fin. Une configuration incomplète place la synchronisation technique en `failed` sans empêcher la validation du dossier.

La suppression d’une prestation déjà synchronisée ne supprime pas automatiquement la mission distante ; elle doit être annulée manuellement dans Planning Guides.
