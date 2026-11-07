# Déploiement, sauvegarde et retour arrière

Serveur : `takada-travel-portail@187.127.106.2`, application dans `~/htdocs/portail.takada-travel.com` (dépôt git, `HEAD` = `743869d` le 2026-11-07 ; le code est aussi modifié par copie directe de fichiers).
Environnement mesuré le 2026-11-07 : PHP 8.4.27, Laravel 13.17, MySQL 8.4, queue Redis.
**Aucune étape de ce document n'est exécutée en production sans l'autorisation explicite de Maxime.**

## 0. Fichiers sensibles présents en production (relevé du 2026-11-07, noms seulement)

`.env`, `.env.save`, `storage/google-credentials.json`, `storage/app/google-credentials.json`, ainsi qu'un dump SQL `storage/app/backup-tea-20261030-0641.sql`. Le dossier `~/backups` est en `drwxrwx---` : le groupe y a accès.

### Procédure de sécurisation (rien n'est supprimé avant décision)
1. **Inventaire en lecture seule**, sans afficher aucun contenu : `ls -l` (taille, date, droits) et empreinte `sha256sum` de chaque fichier. Pour `.env.save`, on liste seulement les **noms** de variables qui diffèrent de `.env` :
   `diff <(cut -d= -f1 .env | sort) <(cut -d= -f1 .env.save | sort)`, puis on compare les empreintes des valeurs sans les afficher.
2. **Décision de Maxime** pour chaque fichier : utile (à garder) ou à retirer.
3. **Mise en quarantaine, pas de suppression** : `mkdir -m 700 ~/quarantaine-AAAAMMJJ` puis `mv` des fichiers à retirer (droits `600`). Les dumps SQL utiles sont chiffrés par clé publique (§2), puis l'original en clair est placé en quarantaine.
4. **Droits** : `chmod 700 ~/backups ~/quarantaine-*` et `chmod 600` sur leurs fichiers, `chmod 600 .env`.
5. **Suppression définitive** au bout de 30 jours, seulement sur nouvel accord explicite, avec la liste des fichiers concernés.
6. Contrôle final : aucun `*.sql`, `*.env*` ni identifiant dans `htdocs/…/public` ; `storage/app` n'est pas servi par le web (route `/storage/{path}` limitée au disque public).

## 1. Comparaison production / local (lecture seule)

Elle détecte les fichiers **modifiés**, **présents seulement en prod** et **présents seulement en local**. Les fins de ligne sont normalisées, et les secrets, `vendor/`, `node_modules/` et `storage/` sont exclus.

```bash
LIST='find app routes config database lang resources bootstrap/app.php bootstrap/providers.php public composer.json composer.lock package.json vite.config.js \
  -type f ! -path "public/storage/*" ! -path "public/build/*" ! -name ".env*" ! -name "*.pem" ! -name "*.key" ! -name "*credentials*" \
  | LC_ALL=C sort | while read f; do echo "$f $(tr -d "\r" < "$f" | md5sum | cut -c1-32)"; done'
ssh -i ~/.ssh/id_ed25519 takada-travel-portail@187.127.106.2 "cd htdocs/portail.takada-travel.com && $LIST" > prod.lst
bash -c "$LIST" > local.lst
echo "== modifiés";          LC_ALL=C join prod.lst local.lst | awk '$2!=$3{print $1}'
echo "== seulement en prod";  LC_ALL=C join -v1 prod.lst local.lst | awk '{print $1}'
echo "== seulement en local"; LC_ALL=C join -v2 prod.lst local.lst | awk '{print $1}'
```

Les fichiers `*.bak*`, `*.orig` et `*.disabled` qui apparaissent seulement en prod sont listés pour information ; ils ne bloquent pas le déploiement. Tout autre écart doit être compris et rapatrié **avant** le déploiement.

## 2. Sauvegardes (stockage protégé)

- **Où :** `~/backups/AAAAMMJJ-HHMM/` sur le serveur, dossier en `700` et fichiers en `600`. Jamais dans `htdocs`, jamais dans `storage/`, jamais dans le dépôt git, jamais dans un dossier synchronisé (OneDrive, Dropbox…).
- **Base : chiffrement par clé publique.** Le serveur ne détient **que la clé publique**. La clé privée et sa phrase secrète restent chez Maxime (gestionnaire de mots de passe et copie hors ligne), jamais sur le serveur ni dans le dépôt.
  - Une seule fois, en local : `gpg --quick-generate-key "Takada sauvegardes <contact@takada-travel.com>" rsa4096 encrypt never`, puis `gpg --export --armor <empreinte> > takada-backup.pub`. Sur le serveur : `gpg --import takada-backup.pub` (clé publique uniquement).
  - À chaque sauvegarde :
  ```bash
  ssh … 'umask 077; D=~/backups/$(date +%Y%m%d-%H%M); mkdir -p $D && cd ~/htdocs/portail.takada-travel.com && set -a && . ./.env >/dev/null 2>&1; set +a; \
    MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --no-tablespaces --routines --triggers --skip-lock-tables -h "${DB_HOST:-127.0.0.1}" -u "$DB_USERNAME" "$DB_DATABASE" \
    | gzip | gpg --batch --yes --trust-model always --encrypt --recipient <empreinte> -o $D/db.sql.gz.gpg && test -s $D/db.sql.gz.gpg'
  ```
  Aucun fichier SQL en clair n'est écrit sur disque (le flux passe directement de `mysqldump` à `gpg`). Le déchiffrement (`gpg --decrypt`) se fait seulement sur le poste de Maxime.
- **Code : archive sans aucun secret.** Les exclusions sont placées **avant** l'archivage (GNU tar), puis le contenu est contrôlé et l'archive est refusée au moindre doute (commande vérifiée le 2026-11-07 avec GNU tar 1.35 sur un dossier piégé : `.env`, `.env.save`, `*.pem`, `*credentials*`, `*.sql`, `storage/`, `vendor/` ont bien été exclus) :
  ```bash
  tar --exclude='./.env*' --exclude='./storage' --exclude='./vendor' --exclude='./node_modules' --exclude='./.git' --exclude='./public/storage' \
      --exclude='*.pem' --exclude='*.key' --exclude='*credentials*' --exclude='*.sql' --exclude='*.sql.gz' --exclude='*.gpg' \
      -czf $D/code.tgz -C ~/htdocs/portail.takada-travel.com .
  if tar tzf $D/code.tgz | grep -Eiq '(^|/)\.env|credential|\.pem$|\.key$|\.sql(\.gz)?$|\.gpg$|^\./storage/|^\./vendor/'; then
    echo "ARCHIVE REFUSÉE : contenu sensible détecté"; rm -f $D/code.tgz; exit 1
  fi
  ```
  Les secrets (`.env`, identifiants Google) restent en place sur le serveur. Ils ne sont pas touchés par un déploiement et ne sont jamais archivés. Le code de référence reste aussi disponible dans git (commit déployé noté dans `$D/marqueurs.txt`).
- **Repères de données** pris juste avant le déploiement, pour savoir ce qui a été créé après : `SELECT MAX(id)` sur `folders`, `folder_items`, `folder_passengers`, `folder_histories`, `folder_messages` et `folder_tasks`. Ils sont notés dans `$D/marqueurs.txt`.
- **Rétention :** 30 jours sur le serveur. La sauvegarde automatique existante (toutes les 6 h vers Google Drive) reste en place.
- **Copie locale pour les tests** (restauration, rejeu des migrations) : déchiffrée seulement dans un dossier temporaire, puis supprimée après usage, de même que la base de test restaurée.

## 3. Avant chaque déploiement

1. Comparaison prod/local (§1) : aucun écart non expliqué.
2. Tests verts en local sur SQLite **et** MySQL 8.4.
3. Migrations du lot rejouées **et annulées** sur une copie restaurée de la prod ; `folders:origin-report` identique avant et après.
4. Sauvegarde chiffrée et repères (§2).
5. Autorisation explicite de Maxime.

## 4. Retour arrière sans perdre les commandes enregistrées après le déploiement

Principe : **on ne restaure jamais un dump antérieur sur la base en service** dès que des utilisateurs ont pu écrire. On revient d'abord en arrière sur le code, et le schéma reste compatible.

1. **Correctif rapide** (préféré) : si le défaut est localisé, un correctif est déployé et testé ; rien n'est restauré.
2. **Retour du code seul** : redéployer le code précédent (git `checkout` du commit précédent, ou extraction de `code.tgz`), puis `php artisan optimize:clear` et `php artisan queue:restart`. Les migrations des lots 2 et 3a sont **additives**, mais l'ancien code ne renseigne pas `folders.origin`. Avant de remettre l'ancien code, il faut donc rétablir temporairement une valeur par défaut compatible :
   ```sql
   ALTER TABLE folders ALTER origin SET DEFAULT 'b2b';
   ```
   C'est sans risque pour les créations, car l'ancien code ne crée que des dossiers agence. La valeur par défaut est retirée (`ALTER TABLE folders ALTER origin DROP DEFAULT`) au redéploiement du nouveau code. Les données saisies entre-temps sont conservées.

   **Aucun dossier interne ne peut devenir visible par une agence pendant ce retour arrière**, même avec l'ancien code qui ignore la notion d'origine. Trois garde-fous en base, indépendants du code, y veillent :
   - la contrainte `folders_internal_has_no_owner_agency` refuse tout `agency_id` sur un dossier interne : un enregistrement de l'ancien formulaire admin sur une fiche interne échoue au lieu d'y attacher une agence ;
   - le trigger `folders_origin_immutable` (livré avec le lot 3b) refuse toute modification de `origin` après création, y compris par une requête SQL manuelle ;
   - l'ancien panel agence filtre sur `agency_id = agence du compte` : un dossier interne (`agency_id` NULL) n'y apparaît jamais.
   Les dossiers internes restent visibles dans l'ancien back-office. On recommande donc de limiter le retour du code seul à quelques heures et de prévenir l'équipe de ne pas modifier les dossiers internes pendant ce laps de temps.
   **Interdit** : toute requête de « réparation » du type `UPDATE folders SET origin = 'b2b'` ou `SET agency_id = …` sur des dossiers internes. Ce cas n'est pas prévu par la procédure ; s'il se présentait, il demanderait l'accord explicite de Maxime, dossier par dossier.
3. **Retour du schéma** (`php artisan migrate:rollback --step=N`) seulement si le défaut vient du schéma. Les migrations refusent elles-mêmes de revenir en arrière si cela supprimait des dossiers internes ou des données saisies (dossiers sans dates, par exemple). Dans ce cas, on applique le point 2.
4. **Restauration de la sauvegarde** : en dernier recours seulement, et seulement après avoir exporté toutes les lignes créées ou modifiées depuis les repères (`id > marqueur` ou `updated_at > heure du déploiement`) pour les réimporter ensuite. Mise en maintenance (`php artisan down`) pendant l'opération. Cette procédure exige l'accord de Maxime au moment même.

## 5. Test de restauration (fait le 2026-11-07)

Dump de prod (3,2 Mo, 37 tables) restauré dans une instance MySQL 8.4 locale temporaire en 1,1 s : comptages identiques à la prod (5 dossiers, 26 prestations, 5 voyageurs, 200 historiques, 2 agences, 9 utilisateurs, 108 produits, 108 migrations). La copie locale du dump a été supprimée après usage.
