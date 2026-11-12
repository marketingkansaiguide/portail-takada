#!/usr/bin/env bash
# Déploiement du suivi billetterie : 4 dates de suivi, écran « Récap réservations », commande de création des produits.
# Sauvegarde base + copies .bak-20261112 avant toute écriture. Arrêt à la première erreur.
# N'IMPORTE AUCUN DOSSIER : l'import réel du récap Excel n'est pas déployé.
set -euo pipefail
cd "$(dirname "$0")"
KEY="$HOME/.ssh/id_ed25519"
HOST=takada-travel-portail@187.127.106.2
SSH="ssh -i $KEY $HOST"
F="app/Models/FolderItem.php app/Support/PermissionCatalog.php app/Filament/Pages/RecapReservations.php resources/views/filament/pages/recap-reservations.blade.php database/migrations/2026_11_11_100000_add_followup_dates_to_folder_items.php app/Console/Commands/AddBilletterieProducts.php"
git archive HEAD $F > /tmp/deploy-20261112.tar
scp -i "$KEY" /tmp/deploy-20261112.tar $HOST:/tmp/deploy-20261112.tar
scp -i "$KEY" storage/app/public/products/studio-nin.png $HOST:/tmp/studio-nin.png
$SSH "FILES='$F' bash -s" <<'REMOTE'
set -euo pipefail
umask 077
cd ~/htdocs/portail.takada-travel.com
D=~/backups/$(date +%Y%m%d-%H%M)-predeploy-billetterie; mkdir -p "$D"
set -a; . ./.env >/dev/null 2>&1; set +a
MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --no-tablespaces --routines --triggers --skip-lock-tables -h "${DB_HOST:-127.0.0.1}" -u "$DB_USERNAME" "$DB_DATABASE" | gzip > "$D/db.sql.gz"
test -s "$D/db.sql.gz"; ls -l "$D"
echo "products avant: $(php artisan tinker --execute='echo App\Models\Product::count();' | tail -1)"
for f in $FILES; do [ -f "$f" ] && cp -p "$f" "$f.bak-20261112"; done
tar xf /tmp/deploy-20261112.tar
mkdir -p storage/app/public/products && cp /tmp/studio-nin.png storage/app/public/products/studio-nin.png
php artisan migrate --force
php artisan optimize:clear && php artisan optimize && php artisan queue:restart
echo "--- essai à blanc de la création des produits"
php artisan catalogue:add-billetterie-products --dry-run | tail -3
echo "--- création réelle"
php artisan catalogue:add-billetterie-products | tail -3
echo "products après: $(php artisan tinker --execute='echo App\Models\Product::count();' | tail -1)"
rm -f /tmp/deploy-20261112.tar /tmp/studio-nin.png
php artisan migrate:status | tail -2
REMOTE
echo "Déploiement terminé."
