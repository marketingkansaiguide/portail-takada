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

## Vérification de la déduplication (extension Planning Guides 1.53.28, 2026-11-07)

Lecture du code de `personnel-management.php` :

- `POST /pm/v1/missions` (`pm_api_upsert_mission`) cherche d'abord la mission par `(external_source, external_reference)`. Si elle existe, il la **met à jour**, sans toucher au guide affecté ni au statut ; sinon il l'insère. La table `pm_availabilities` porte un **index unique** sur ce couple. En cas d'insertion concurrente, l'insertion échoue et la mission existante est relue et renvoyée. Un renvoi après une panne (création réussie côté WordPress, enregistrement local perdu) ne crée donc **pas** de doublon. Ce cas est testé côté portail (`tests/Feature/Lot3b/ExternalSyncTest.php`).
- L'identifiant renvoyé est `external_mission_id` (stable), et non l'`id` de ligne.
- Le champ `agency` est obligatoire. Pour un dossier interne, le portail envoie l'agence associée ou « Takada Travel (dossier interne) ».
- **Anomalie à corriger côté WordPress (concerne aussi le B2B)** : la modification d'une mission dans l'administration de Planning Guides supprime les lignes puis les recrée, ce qui change leur `id` mais conserve `external_mission_id`. Or `POST /missions/{id}/cancel` (`pm_api_cancel_mission`) cherche `WHERE id = {mission_id}`. Une annulation envoyée par le portail après une telle modification répond donc 404 (« Mission introuvable ») : elle est marquée en échec côté portail, mais rien n'est annulé dans le planning. Correctif proposé : `WHERE (id = %d OR external_mission_id = %d)`.
