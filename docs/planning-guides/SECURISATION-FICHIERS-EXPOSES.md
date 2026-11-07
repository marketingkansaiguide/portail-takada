# Planning Guides — sécurisation des fichiers exposés (procédure prête, non exécutée)

Préparée le 2026-11-07. **Aucune étape n'est exécutée sans l'accord explicite de Maxime.** Rien n'est supprimé : tout ce qui est retiré passe en quarantaine.

## Constat (lecture seule)
| Fichier (dans `wp-content/plugins/personnel-management/`) | Accès public | Utile ? | Action |
|---|---|---|---|
| `credentials.json` (clé privée d'un compte de service Google) | **oui** (200) | **oui** (Google Drive) | Nouvelle clé hors du dossier web, ancienne révoquée |
| `personnel-management.php2` (ancienne copie du code, 663 Ko) | **oui**, lisible en clair | non | Quarantaine |
| `dist/` (6 anciennes versions, 1.40 à 1.46) | oui | non (seul un test de développement s'en sert) | Quarantaine |
| `tests/`, `composer.json`, `composer.lock` | oui | non | Quarantaine |
| `.git/` | non (403), index corrompu | non | Quarantaine |
| `vendor/` (bibliothèque Google) | oui (ex. `installed.json`) | **oui** | Garder, bloquer l'accès web |
| `includes/missing-availability.php` | — | aucun fichier actif ne le charge | **À confirmer par Maxime** : rien n'est fait |

Ces fichiers passent par le cache Cloudflare (`cf-cache-status: HIT`) : **il faut vider le cache** après l'opération, sinon les copies restent servies.

## Pré-requis
- Planning Guides **1.53.29** installé (procédure : `CORRECTIF-1.53.29.md`). Cette version lit la clé à l'emplacement donné par la constante `PM_GOOGLE_CREDENTIALS` de `wp-config.php`, et garde l'ancien emplacement tant que la constante n'est pas définie. Testé en local : avec la constante, sans, et avec un fichier absent (repli sur l'ancien emplacement).
- Sauvegarde du jour (base + dossier de l'extension + `wp-config.php`) dans `~/backups/AAAAMMJJ-securisation/` (droits `700`).

## Étapes
1. **Nouvelle clé (Maxime, console Google Cloud)** : sur le compte de service indiqué dans `client_email` de l'ancienne clé, créer une nouvelle clé JSON. L'ancienne n'est **pas encore** révoquée.
2. **Ranger la nouvelle clé hors du web** : `mkdir -m 700 ~/private`, déposer le fichier en `~/private/google-sa.json`, puis `chmod 600`.
3. **Déclarer l'emplacement** dans `wp-config.php` (après sauvegarde du fichier) : `define('PM_GOOGLE_CREDENTIALS', '/home/rq8awgg/private/google-sa.json');`
4. **Tester Google Drive** sur une mission de test : ajouter, puis supprimer un fichier joint. Le journal de l'extension ne doit afficher aucune erreur `credentials`.
5. **Quarantaine** : `mkdir -m 700 ~/quarantaine-AAAAMMJJ/personnel-management`, puis `mv` de `credentials.json`, `personnel-management.php2`, `dist`, `tests`, `composer.json`, `composer.lock` et `.git` dans ce dossier.
6. **Bloquer `vendor/`** : y créer un `.htaccess` contenant `Require all denied`. Ces fichiers sont chargés par PHP, jamais par le navigateur. Vérifier par `curl` que la réponse est 403. Si l'hébergeur ignore les `.htaccess`, on passe par une règle de l'hébergeur ou de Cloudflare sur ce chemin.
7. **Vider le cache** Cloudflare / Rocket.net (panneau Rocket.net, « Purge CDN cache »), au minimum pour ces adresses.
8. **Révoquer l'ancienne clé** dans Google Cloud, une fois l'étape 4 réussie.

## Vérifications après opération
- `credentials.json`, `personnel-management.php2`, `composer.json` et `dist/…` répondent 404. `vendor/composer/installed.json` répond 403.
- Le site répond 200. L'API des missions répond 401 sans clé. L'administration de Planning Guides s'ouvre.
- Une mission de test peut recevoir un fichier joint (Google Drive) avec la nouvelle clé.
- `~/public_html/error_log` est surveillé pendant 30 minutes.

## Retour arrière
Remettre par `mv` les fichiers depuis la quarantaine, retirer la ligne `PM_GOOGLE_CREDENTIALS` de `wp-config.php` (sauvegarde), puis supprimer `vendor/.htaccess`. Tant que l'ancienne clé n'est pas révoquée, l'ancien fonctionnement est rétabli à l'identique. **Une fois révoquée, l'ancienne clé ne fonctionne plus** : le retour arrière doit alors garder la nouvelle clé, avec ou sans la constante.

## Remarque liée au portail
Les missions du portail sont identifiées par `folder_item_<id>`. Si la base du portail était un jour restaurée **et** que de nouvelles prestations étaient créées ensuite, un même numéro pourrait désigner deux prestations différentes, et Planning Guides mettrait à jour la mauvaise mission. Après toute restauration du portail, le compteur d'identifiants de `folder_items` doit donc repartir au-dessus du maximum connu côté WordPress. C'est ajouté à `docs/DEPLOIEMENT.md`.
