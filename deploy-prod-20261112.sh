#!/usr/bin/env bash
# Synchronisation fournisseurs Excel : crée les fournisseurs manquants et les assigne aux produits.
# Sauvegarde base dans ~/backups, dépose la commande, simulation puis écriture. Arrêt à la première erreur.
set -euo pipefail
cd "$(dirname "$0")"
SSH="ssh -i $HOME/.ssh/id_ed25519 takada-travel-portail@187.127.106.2"
F="app/Console/Commands/SyncSuppliersFromExcel.php"
git archive HEAD $F > /tmp/deploy-20261112.tar
scp -i "$HOME/.ssh/id_ed25519" /tmp/deploy-20261112.tar takada-travel-portail@187.127.106.2:/tmp/deploy-20261112.tar
$SSH "bash -s" <<'REMOTE'
set -euo pipefail
umask 077
cd ~/htdocs/portail.takada-travel.com
D=~/backups/$(date +%Y%m%d-%H%M)-suppliers; mkdir -p "$D"
set -a; . ./.env >/dev/null 2>&1; set +a
MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --no-tablespaces --skip-lock-tables -h "${DB_HOST:-127.0.0.1}" -u "$DB_USERNAME" "$DB_DATABASE" | gzip > "$D/db.sql.gz"
test -s "$D/db.sql.gz"; ls -l "$D"
tar xf /tmp/deploy-20261112.tar && rm -f /tmp/deploy-20261112.tar
php artisan suppliers:sync-excel
php artisan suppliers:sync-excel --apply
echo "--- vérification (2e passage : doit tout trouver déjà fait)"
php artisan suppliers:sync-excel | tail -3
REMOTE
echo "Terminé."
