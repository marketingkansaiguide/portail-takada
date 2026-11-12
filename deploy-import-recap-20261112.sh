#!/usr/bin/env bash
# Import réel du récap billetterie en production (feu vert de Maxime le 2026-11-12).
# Sauvegarde base avant, essai à blanc, puis --apply. Le fichier Excel est supprimé du serveur à la fin.
#
# RETOUR ARRIÈRE (ne touche que ce qui porte un repère d'import ; à exécuter sur le serveur) :
#   DELETE FROM folder_items WHERE import_ref IS NOT NULL;
#   DELETE FROM folder_planned_items WHERE source = 'import_recap';
#   DELETE FROM folders WHERE import_key IS NOT NULL;
#   DELETE FROM agencies WHERE import_key = 'recap' AND id NOT IN (SELECT agency_id FROM folders WHERE agency_id IS NOT NULL);
#   (ou restauration complète de la base : ~/backups/*-preimport-billetterie/db.sql.gz)
set -euo pipefail
cd "$(dirname "$0")"
KEY="$HOME/.ssh/id_ed25519"
HOST=takada-travel-portail@187.127.106.2
SSH="ssh -i $KEY $HOST"
F="app/Console/Commands/ImportBilletterieRecap.php database/migrations/2026_11_12_100000_add_import_key_to_folders_and_items.php"
git archive HEAD $F > /tmp/deploy-import.tar
scp -i "$KEY" /tmp/deploy-import.tar $HOST:/tmp/deploy-import.tar
scp -i "$KEY" "/c/Users/marke/Downloads/[2026] Billetterie (3).xlsx" $HOST:/tmp/billetterie.xlsx
$SSH "FILES='$F' bash -s" <<'REMOTE'
set -euo pipefail
umask 077
cd ~/htdocs/portail.takada-travel.com
D=~/backups/$(date +%Y%m%d-%H%M)-preimport-billetterie; mkdir -p "$D"
set -a; . ./.env >/dev/null 2>&1; set +a
MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --no-tablespaces --routines --triggers --skip-lock-tables -h "${DB_HOST:-127.0.0.1}" -u "$DB_USERNAME" "$DB_DATABASE" | gzip > "$D/db.sql.gz"
test -s "$D/db.sql.gz"; ls -l "$D"
for f in $FILES; do [ -f "$f" ] && cp -p "$f" "$f.bak-20261112c"; done
tar xf /tmp/deploy-import.tar
php artisan migrate --force
php artisan optimize:clear >/dev/null && php artisan optimize >/dev/null
echo "--- avant : folders=$(php artisan tinker --execute='echo DB::table("folders")->count();' | tail -1) items=$(php artisan tinker --execute='echo DB::table("folder_items")->count();' | tail -1)"
echo "--- ESSAI À BLANC"
php artisan billetterie:import-recap /tmp/billetterie.xlsx | grep -E "Dossiers à créer|dossiers internes|dossiers agence|Agences|sans produit|Essai"
echo "--- IMPORT RÉEL"
php artisan billetterie:import-recap /tmp/billetterie.xlsx --apply | sed -n '/ÉCRITURE/,$p'
echo "--- après : folders=$(php artisan tinker --execute='echo DB::table("folders")->count();' | tail -1) items=$(php artisan tinker --execute='echo DB::table("folder_items")->count();' | tail -1)"
rm -f /tmp/billetterie.xlsx /tmp/deploy-import.tar
php artisan optimize:clear >/dev/null && php artisan optimize >/dev/null && php artisan queue:restart
REMOTE
echo "Import terminé."
