# Planning Guides 1.53.29 — correctif d'annulation des missions du portail

Statut : **préparé et testé en local uniquement**. Rien n'est installé en production.

## Problème (reproduit)
Avec la version 1.53.28, l'enregistrement d'une mission depuis l'administration (affectation d'un guide ou modification) supprime la ligne puis la recrée. La mission reçoit donc un **nouvel `id`**, alors que le portail garde `external_mission_id`, qui correspond à l'ancien `id`. Or l'API d'annulation (`POST /pm/v1/missions/{id}/cancel`) cherche `WHERE id = {id}` : elle répond **404**. La mission reste « réservé » avec son guide, et le portail la marque en échec (`resultat-1.53.28-avant.txt`).

## Correctif (diff : `planning-guides-1.53.29.diff`, 3 modifications)
1. **Identifiant d'origine conservé** : quand une mission **liée au portail** (`external_source` renseigné) est recréée par l'édition, sa première ligne reprend son `id` d'origine (`external_mission_id`), à condition qu'il soit libre. La ligne d'origine est supprimée juste avant, dans la même transaction. Le guide affecté, son acceptation ou son refus, le statut, la rémunération, l'historique, les fichiers, les rappels et les notifications suivent exactement le même code qu'avant : seul l'`id` inséré change. Les missions créées directement dans WordPress ne sont pas concernées.
2. **Annulation tolérante** : l'API d'annulation retrouve aussi une mission dont l'`id` a déjà changé avant le correctif (`id = X OR external_mission_id = X`), toujours limitée à `external_source = 'ticketing_portal'` et au même `ticketing_order_item_id`.
3. Version 1.53.29.

Aucune modification de table, aucune suppression de données. `external_source` et `external_reference` ne changent pas.

## Tests de bout en bout (WordPress local 6.x + extension + portail Laravel local)
Script : `e2e/e2e.php` (simulation de l'admin WordPress : `e2e/wp-admin-edit.php`, qui appelle la même fonction que la modale d'édition).

| Étape | 1.53.28 | 1.53.29 |
|---|---|---|
| 1-2. Le portail crée la mission ; WordPress la reçoit | OK | OK |
| 3. Un guide est affecté dans WordPress | OK (id 20 → 21) | OK (id conservé) |
| 4. La mission est modifiée dans WordPress | OK (id → 22) | OK (id conservé) |
| 5. Le portail met à jour la mission (1 seule ligne, guide et statut conservés) | OK | OK |
| 6-7. Le portail annule, WordPress annule la bonne mission | **KO (HTTP 404, reste « réservé »)** | OK |
| 8. Aucun doublon ; guide, rémunération et référence conservés | OK | OK |
| Annulation renvoyée une 2e fois | **KO (404)** | OK |
| Panne réseau à la création, puis reprise et rejeu | OK (0 puis 1 ligne) | OK |
| Mission existante dont l'`id` a déjà changé (laissée par 1.53.28) | — | OK (annulée, guide conservé) |
| Mission créée directement dans WordPress, puis modifiée | comportement inchangé | comportement inchangé |

1.53.29 : 3 exécutions consécutives, toutes OK (`e2e/resultat-1.53.29-*.txt`).

État de la production (lecture seule, 2026-11-07) : 4 missions du portail, toutes annulées, aucune avec un `id` déjà changé.

## Erreur critique de `wp` en ligne de commande (diagnostic en lecture seule)
- Cause : sur le serveur (CloudLinux), la **ligne de commande utilise PHP 7.4** (`~/.cl.selector/defaults.cfg` : `php=7.4`), alors que le site tourne en PHP 8.2. L'extension utilise une syntaxe PHP 8 (`match`, ligne 881) : WP-CLI s'arrête sur une erreur de syntaxe dès qu'il charge les extensions. Le site web n'est pas affecté.
- Tâches planifiées : `DISABLE_WP_CRON` n'est pas défini et il n'y a pas de crontab utilisateur. WP-Cron est donc déclenché par le web (PHP 8.2). La liste des événements (lue avec `/opt/alt/php82/usr/bin/php /usr/local/bin/wp cron event list`) montre des échéances normales, sans retard : `pm_flush_mail_outbox`, `pm_mission_reminder_cron`, `pm_invoice_cron`, `pm_daily_ticketing_reminder_event`, etc.
- Affecté : toute opération de maintenance lancée avec `wp` (mises à jour, export, commandes manuelles), et tout outil de l'hébergeur qui passerait par WP-CLI.
- Contournement immédiat, sans rien modifier : `/opt/alt/php82/usr/bin/php /usr/local/bin/wp …`.
- Correction proposée (à autoriser) : passer la version PHP de la ligne de commande à 8.2 dans le sélecteur PHP de l'hébergeur, pour l'aligner sur le site.

## Installation (après autorisation)
Sauvegarde du fichier et de la table `wp_pm_availabilities`, remplacement de `personnel-management.php`, contrôle de la version, puis un test d'annulation sur une mission de test.

## Contrôle préalable au déploiement (2026-11-07, lecture seule en production)

**Sauvegarde vérifiée** (conservée sur le poste de Maxime, pas en production) :
- Base WordPress : export complet de 103 Mo, restauré dans une base locale. On y retrouve 222 tables, 3 543 missions (id max 5173), 47 guides, 4 363 contenus et 59 utilisateurs, ainsi qu'une empreinte des missions **identique** à celle calculée en production.
- Extension en place : archive du dossier `personnel-management` téléchargée telle quelle. Le fichier principal correspond exactement à 1.53.28 (md5 `64e9db1c…`).

**Diff exact** : seul `personnel-management.php` change, sur les 3 points ci-dessus (22 lignes, aucune autre modification fonctionnelle).
⚠️ **Le dossier de l'extension en production n'est pas identique au zip fourni** : 4 fichiers y sont plus récents (`css/pm-styles.css` et `includes/invoices/{service,settings,stats}.php`), et la prod contient aussi `vendor/`, `dist/`, `tests/`, `includes/missing-availability.php` et `credentials.json`. **Le déploiement doit donc remplacer UNIQUEMENT `personnel-management.php`**, jamais le dossier entier ni le zip.

**Missions existantes** : sur une copie locale de la production (e-mails et appels externes bloqués), même scénario joué avec 1.53.28 puis avec 1.53.29 : chargement de WordPress, `init`, puis exécution des tâches planifiées `pm_mission_reminder_cron`, `pm_invoice_cron`, `pm_daily_ticketing_reminder_event`, `pm_flush_mail_outbox` et `pm_visa_expiration_cron`. Les 3 543 missions sont **strictement identiques** dans les deux versions : 0 ligne modifiée, ajoutée ou supprimée par le correctif. La seule évolution, commune aux deux versions, est le marquage `reminder_sent` de 3 missions par la tâche de rappel habituelle. La version de schéma (`pm_plugin_db_version` = 1.66) ne change pas : la montée de version ne déclenche aucune migration.

## Procédure d'installation (à exécuter seulement après autorisation)
1. Juste avant : export de la base (`/opt/alt/php82/usr/bin/php /usr/local/bin/wp db export`) vers `~/backups/AAAAMMJJ-planning-guides/` (droits `700`), et copie de `personnel-management.php` en `personnel-management.php.1.53.28` dans ce même dossier de sauvegarde, **hors** du dossier web.
2. Contrôle d'empreinte : le fichier en place doit toujours avoir le md5 `64e9db1c…`. Sinon, on s'arrête et on refait le diff.
3. Envoi du fichier 1.53.29 dans un fichier temporaire, contrôle de syntaxe avec `/opt/alt/php82/usr/bin/php -l`, puis remplacement par un `mv` atomique.

## Tests après mise à jour
1. Le site répond (HTTP 200) et l'administration de Planning Guides s'ouvre.
2. `wp plugin get personnel-management --field=version` (avec PHP 8.2) renvoie `1.53.29`.
3. L'API refuse un appel sans clé (401). Le nombre de missions et leur empreinte n'ont pas bougé.
4. Une mission de **test** créée par l'API avec une référence `recette-1.53.29` : affectation d'un guide dans l'admin, modification, puis annulation par l'API, qui doit répondre OK avec la mission au statut « annulé ». La mission de test est ensuite laissée annulée, sans suppression. Cette étape fait partie du « test réel et contrôlé » demandé avant l'activation de `GUIDE_SYNC_INTERNAL_ORDERS`.
5. Surveillance de `~/public_html/error_log` pendant 30 minutes.

## Retour arrière vers 1.53.28
Remettre le fichier sauvegardé à l'étape 1 (`mv` atomique), vérifier la version, puis reprendre les tests 1 à 3. Le correctif ne modifie ni le schéma ni les données : **aucune restauration de base n'est nécessaire**. Les missions recréées entre-temps par l'édition auront simplement conservé leur identifiant d'origine, ce qui est compatible avec 1.53.28.

## WP-CLI : méthode recommandée
- Ne pas modifier le sélecteur PHP de l'hébergeur (le site reste en 8.2, et la ligne de commande par défaut reste en 7.4).
- Créer un petit lanceur, réservé au compte `rq8awgg` : `~/bin/wp` contenant `exec /opt/alt/php82/usr/bin/php /usr/local/bin/wp "$@"`. Le dossier `~/bin` est déjà en tête du `PATH` (`.bashrc`), mais il n'existe pas encore.
- Effet : la commande `wp` utilise PHP 8.2 pour ce seul compte. Rien ne change pour le site web, pour WP-Cron (déclenché par le web, aucune crontab) ni pour les autres commandes `php`. Le script de maintenance `~/add-missing-missions-v2.php`, qui s'utilise avec `wp eval-file` et qui ne peut donc pas fonctionner aujourd'hui, redevient utilisable. Il passe le contrôle de syntaxe en PHP 7.4 comme en 8.2.
- Retour arrière : supprimer `~/bin/wp`.
