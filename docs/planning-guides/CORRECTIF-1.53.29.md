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
