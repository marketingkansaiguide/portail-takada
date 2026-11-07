# Déploiement, sauvegarde et restauration

Serveur : `takada-travel-portail@187.127.106.2`, application dans `~/htdocs/portail.takada-travel.com`.
Environnement mesuré le 2026-11-07 : PHP 8.4.27, Laravel 13.17, MySQL 8.4, queue Redis.

## 1. Avant chaque déploiement d'un lot

1. **Vérifier qu'il n'y a pas d'écart entre prod et local** (empreintes, fins de ligne normalisées) :
   ```bash
   CMD='find app routes config database/migrations resources/views bootstrap/app.php composer.lock -type f ! -name "*.bak*" ! -name "*.orig" ! -name "*.disabled" | sort | while read f; do echo "$f $(tr -d "\r" < "$f" | md5sum | cut -c1-32)"; done'
   ssh -i ~/.ssh/id_ed25519 takada-travel-portail@187.127.106.2 "cd htdocs/portail.takada-travel.com && $CMD" > prod.lst
   bash -c "$CMD" > local.lst
   LC_ALL=C join <(LC_ALL=C sort prod.lst) <(LC_ALL=C sort local.lst) | awk '$2!=$3{print $1}'
   ```
   Tout écart doit être compris et rapatrié avant de continuer.
2. **Sauvegarde de la base** (lecture seule, sans verrou) :
   ```bash
   ssh -i ~/.ssh/id_ed25519 takada-travel-portail@187.127.106.2 'cd htdocs/portail.takada-travel.com && set -a && . ./.env >/dev/null 2>&1; set +a; MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --no-tablespaces --routines --skip-lock-tables -h "${DB_HOST:-127.0.0.1}" -u "$DB_USERNAME" "$DB_DATABASE"' > backup-AAAAMMJJ-HHMM.sql
   ```
   Le fichier contient des données personnelles : à conserver hors du dépôt et à supprimer quand il n'est plus utile. La sauvegarde automatique toutes les 6 h vers Google Drive (`backups:database-to-google-drive`) reste en place.
3. **Archive du code déployé** : `tar czf ~/releases/portail-AAAAMMJJ.tgz --exclude=vendor --exclude=storage --exclude=node_modules -C ~/htdocs portail.takada-travel.com`.
4. Tests verts en local sur la branche du lot, **et** migrations rejouées sur une copie restaurée de la base de prod (§2).

## 2. Test de restauration (fait le 2026-11-07)

- Dump de prod (3,2 Mo, 37 tables) restauré dans une instance MySQL 8.4 locale et temporaire (base `portail_restore_test`, port 3307) en **1,1 s**.
- Comptages identiques à la prod : 5 dossiers, 26 prestations, 5 voyageurs, 200 lignes d'historique, 2 agences, 9 utilisateurs, 108 produits, 108 migrations.
- `php artisan migrate:status` sur la copie restaurée : 108 migrations exécutées, aucune en attente.

Restauration en prod (en dernier recours, après accord) : mettre l'application en maintenance (`php artisan down`), réimporter le dump (`mysql … < backup.sql`), redéployer l'archive de code correspondante, `php artisan optimize:clear`, puis `php artisan up`.

## 3. Retour arrière d'un lot

- Lot avec migrations **additives** uniquement : redéployer l'archive précédente, puis `php artisan migrate:rollback --step=<nombre de migrations du lot>`.
- Lot qui **transforme** des données : restauration du dump pris juste avant le déploiement.
- Dans tous les cas : `php artisan optimize:clear` et `php artisan queue:restart` (workers Redis).
