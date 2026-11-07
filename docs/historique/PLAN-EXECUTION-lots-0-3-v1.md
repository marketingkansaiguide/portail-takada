> **Document historique (2026-11-07).** Certaines propositions ici sont remplacées, en particulier le passage automatique du dossier en « confirmé » à partir du statut commercial et le contrôle des automatismes au niveau du dossier. Les règles en vigueur sont dans `docs/PLAN-EXECUTION.md` et `docs/LOT3-preparation-commande.md`.

# Plan d'exécution — Lots 0 à 3 (dossiers internes)

Date : 2026-11-07 · Base : audit `docs/AUDIT-PHASE0-dossiers-internes.md` + décisions de Maxime du 2026-11-07.
Légende : **[C]** constaté dans le code local · **[H]** hypothèse à vérifier.
Règle pour tous les lots : rien n'est migré ni déployé en production sans validation explicite.

---

## État de départ mesuré

- **[C]** Suite de tests locale : `php artisan test` → **22 tests, 21 réussis, 1 échec déjà présent** (`GuideMissionIntegrationTest` : « falls back to the base seasonal purchase price… », 0 obtenu au lieu de 18 000). Il faut le corriger ou le documenter avant d'ajouter des tests, sinon la référence n'est pas propre.
- **[C]** Les tests tournent sur SQLite en mémoire (`phpunit.xml`), la prod sur MySQL (`.env.example`). Toute contrainte SQL spécifique (CHECK, trigger) doit être conditionnée au driver et testée aussi sur MySQL.
- **[C] Comparaison prod/local faite le 2026-11-07 (lecture seule)**, sur `app`, `routes`, `config`, `database/migrations`, `resources/views`, `bootstrap/app.php`, `composer.lock` et `tests`, fins de ligne normalisées :
  - 3 fichiers différents, **sans différence fonctionnelle** : `Folder.php` (`str()->random` vs `Str::random`), `FolderItem.php` (commentaire et ligne vide), `AdminPanelProvider.php` (commentaire).
  - En prod seulement : des fichiers `.bak*`, `.orig` et `.disabled` laissés par des interventions passées, à nettoyer plus tard (archivés, pas supprimés).
  - En local seulement : `tests/Feature/*` (le dossier `tests` n'est pas déployé).
  - Toutes les migrations sont exécutées en prod. Versions : **PHP 8.4.27** (le local exige ^8.3), **Laravel 13.17.0**, **MySQL 8.4** (contraintes CHECK prises en charge), queue **redis**.
  - Volumétrie : 5 dossiers (4 sans segment, 1 billetterie ; 4 terminés, 1 brouillon), tous avec une agence, aucun avec un vendeur interne ; 2 agences dont 1 sans compte ; 26 prestations ; 5 voyageurs dont 2 avec des données alimentaires ou de mobilité ; 200 lignes d'historique.
  - Conséquence : **le lot 0 se réduit à la procédure de sauvegarde/restauration**. La migration de classement porte sur très peu de lignes et peut être vérifiée dossier par dossier.
- **[C]** Test en échec : il est **obsolète**, pas le code. Depuis le commit `3373800`, `ProductOption::purchasePriceForDate` renvoie l'**écart** de l'option (0 ici) et le prix de base vient de `Product::guideBasePurchaseForDate`. La rémunération du guide reste attendue à 18 000. Correction prévue au lot 1 : tester l'écart (0) et la rémunération totale (18 000).

---

## Analyse des automatismes (demande n° 4)

| Automatisme | Déclencheur actuel [C] | Effet d'un dossier interne si on ne change rien | Règle retenue |
|---|---|---|---|
| Notification « nouveau dossier » | `FolderObserver::created`, admins en dur | Notification aux admins | Garder ; ajouter le commercial responsable |
| Notification « dossier confirmé » | `status → confirmed`, envoyée au `main_seller` (**vendeur agence**) sinon admins | Pourrait viser un compte agence si `main_seller_id` est rempli | Dossiers internes : `main_seller_id` interdit, notification au responsable interne |
| Missions guides (WordPress) | `FolderItem` créé/modifié **et** dossier `confirmed`/`completed` **et** catégorie 4 ; annulation sur `cancelled` | Rien tant que le dossier n'est pas confirmé | Garder tel quel : la transmission démarre quand le dossier interne passe à l'état opérationnel (voir mapping ci-dessous) |
| Google Calendar (rappels d'ouverture de réservation) | Création/modification de prestation, si `days_before_opening` et dossier ni annulé ni terminé, **y compris en brouillon** | Rappels créés dès le brouillon interne | Dossiers internes : pas de synchro tant que le dossier n'est pas opérationnel. B2B : inchangé |
| Emails / fax fournisseurs | **Boutons manuels** dans `FolderResource` (aperçu, Google Sheets/fax) | Aucun envoi automatique | Rien à changer : aucun risque d'envoi prématuré |
| Tâches automatiques | `ActionBoardTaskGenerator` toutes les 10 min : « Nouveau dossier à analyser » pour **chaque dossier draft/pending**, prix d'achat manquant, facture, ouverture de réservation, messages agence | Chaque brouillon interne crée une tâche « nouveau dossier à analyser » | Règle « nouveau dossier » limitée aux dossiers B2B ; les autres règles s'appliquent aux internes une fois opérationnels |
| Clôture automatique | Cron quotidien : `draft/pending/confirmed` + `end_date` passée → `completed` | Un brouillon interne daté dans le passé serait clôturé | Cron limité aux dossiers B2B ; pour les internes, passage à « Terminé » seulement depuis « En voyage » ou « Prêt au départ » |
| Chat dossier | Staff qui écrit → email au `main_seller` (agence) | Email vers une agence si un vendeur agence est lié | Chat et documents « partagés avec l'agence » masqués et refusés côté serveur pour les dossiers internes |
| PDF récapitulatif | `pdf.recapitulatif` lit `$agency->name` | **Erreur 500** si aucune agence | Rendre la vue tolérante à `agency = null` (sans changer le rendu B2B) |
| Étiquettes, check-ins, IWA, Seven Tickets, billets de train | Filtrent `status in (confirmed, completed)` | Rien avant confirmation, puis intégration normale | Souhaité : l'opérationnel traite les internes comme les autres une fois confirmés |
| Statistiques | Widgets groupés par `folders.agency_id`, chiffre d'affaires = `total_price` | Internes absents des stats agence, mais présents dans le CA global | Lot ultérieur : stats par `COALESCE(agency_id, client_agency_id)` et filtre par origine |

**Lien entre les deux cycles de statuts.** On garde la colonne `status` (cycle B2B, dont dépendent tous les automatismes) et on ajoute `ops_status` (cycle commercial interne). Pour un dossier interne, `status` est **dérivé** d'`ops_status` par une seule fonction :

| `ops_status` (interne) | `status` technique dérivé | Conséquence |
|---|---|---|
| Brouillon, En préparation | `draft` | Aucun automatisme opérationnel |
| Réservations en cours, Prêt au départ, En voyage | `confirmed` | Guides, check-ins, factures, Calendar s'activent comme pour un dossier B2B confirmé |
| Terminé | `completed` | |
| Annulé | `cancelled` | Missions guides annulées (existant) |

Le passage de « En préparation » à « Réservations en cours » est l'action explicite qui lance les automatismes. Elle demande une confirmation dans l'interface.

---

## Lot 0 — Alignement production / local et filet de sécurité

**Objectif :** partir d'un code identique à la prod et savoir restaurer réellement.

### 0.1 Méthode d'alignement (lecture seule)
1. **Empreintes de fichiers** sans toucher aux données ni au `.env` :
   ```bash
   ssh -i ~/.ssh/id_ed25519 takada-travel-portail@187.127.106.2 "cd htdocs/portail.takada-travel.com && find app routes config database/migrations resources/views bootstrap/app.php composer.lock -type f -print0 | xargs -0 md5sum" > prod.md5
   ```
   Même commande en local, puis comparaison (`diff`) : liste des fichiers modifiés, ajoutés ou absents de chaque côté.
2. Pour chaque fichier divergent : récupération en lecture (`scp` vers un dossier hors dépôt), diff ligne à ligne, décision **garder prod / garder local / fusionner**.
3. `php artisan migrate:status` en prod (lecture seule) pour vérifier qu'aucune migration locale n'est en attente et inversement.
4. Commit de rapatriement sur une branche `align/prod-2026-11` avant tout nouveau développement.
5. Versions en prod : `php -v`, `php artisan --version`, version MySQL/MariaDB (nécessaire pour les contraintes CHECK, prises en charge à partir de MySQL 8.0.16 / MariaDB 10.2).

Étape 1 réalisée le 2026-11-07 (voir « État de départ mesuré ») : aucun écart fonctionnel, donc les étapes 2 à 4 sont sans objet.

### 0.2 Sauvegarde et restauration testées
- Existant [C] : `backups:database-to-google-drive` toutes les 6 h.
- Ajouts : sauvegarde manuelle `mysqldump --single-transaction` **juste avant** chaque déploiement de lot, plus une archive du code déployé (`tar` du dossier hors `vendor/` et `storage/`).
- **Test de restauration** : restaurer le dernier dump sur une base locale vierge, lancer `migrate:status` et un échantillon de requêtes (nombre de dossiers, prestations, utilisateurs), puis noter la durée. On le fait une fois en lot 0, puis avant chaque lot qui touche au schéma.
- Retour arrière d'un lot = redéployer l'archive précédente, puis soit `migrate:rollback --step=N` (migrations additives réversibles), soit restauration du dump si des données ont été transformées.

**Fichiers :** aucun fichier applicatif. Ajout de `docs/DEPLOIEMENT.md` (procédure, commandes, check-list).
**Risque :** écraser un correctif présent seulement en prod → atténué par le diff fichier par fichier.
**Validation :** diff prod/local vide (ou écarts documentés et acceptés) ; restauration réussie sur une base de test.

---

## Lot 1 — Tests de référence (avant tout changement de comportement)

**Objectif :** figer le comportement actuel du B2B pour détecter toute régression.

### 1.1 Remise à niveau de la suite
- Corriger ou expliquer l'échec existant dans `GuideMissionIntegrationTest` (prix d'achat saisonnier de repli).
- Ajouter des factories : `AgencyFactory`, `FolderFactory`, `FolderItemFactory`, `ProductFactory` (+ périodes, prix, options), `RoleFactory`/seeder des profils.

### 1.2 Caractérisation du moteur tarifaire
Le calcul est réparti entre `Product` (`baseRateTotal`, `calculateAgeBasedBreakdown`, périodes de variantes), `ProductOption` (`priceModifierForDate`, `purchasePriceForDate`, tarifs saisonniers) et **deux copies** de `updateItemPrices` (`FolderResource:159` et `AgencyFolderResource:118`).

Tests de caractérisation (valeurs attendues = résultats actuels, figés) :
- période standard / haute saison / plage de dates ; tranches `min_pax`–`max_pax` ; tarif « pax supplémentaire » ;
- tarif global vs par personne ; âges adulte / ado / enfant, âge minimum ;
- options obligatoires, groupes d'options, options à tarif saisonnier, options sans tranche (Extras), multiplicateur (Pocket wifi) ;
- prix d'achat : base, saisonnier, option guide, repli ;
- disponibilité par groupe client (`isAvailableForAgency`) ;
- **équivalence admin/agence** : pour un même jeu d'entrées, `FolderResource::updateItemPrices` et `AgencyFolderResource::updateItemPrices` produisent les mêmes `unit_price`/`total_price`. Les fonctions prennent des closures `$get`/`$set`, testables avec un tableau simulé.

**Extraction en service : pas nécessaire à ce stade.** Les dossiers internes utilisent le formulaire admin existant, donc la même copie de `updateItemPrices`. Aucun code tarifaire n'est modifié dans les lots 1 à 3. L'extraction ne sera utile qu'au module de devis (moteur commun B2B/B2C), et elle se fera alors sous la protection de ces tests.

### 1.3 Frontières d'accès (état actuel)
Pour un compte agence A, avec un dossier de l'agence A et un de l'agence B : liste, édition, suppression (brouillon uniquement), prestations, voyageurs, PDF récapitulatif (refusé au guard agence), fax, étiquettes, fichiers Drive (`folders.drive-file`, `folders.legacy-file`), chat (`FolderChat`), page Contact (choix du dossier), ajout au panier depuis `ViewProduct`, webhook guides (refus sans clé).

### 1.4 Parcours B2B et automatismes
- Agence : création de dossier → ajout de prestation → validation (`pending`) → email `FolderValidatedMail` (`Mail::fake`).
- Admin : création d'un dossier pour une agence, visible ensuite par cette agence.
- Statut `confirmed` → `SyncGuideMission` mis en file (`Queue::fake`) ; `cancelled` → `CancelGuideMission`.
- Cron de clôture : `end_date` passée → `completed`.
- `ActionBoardTaskGenerator` : tâche « nouveau dossier » sur brouillon, prix d'achat manquant.
- Google Calendar : vérifier la décision de synchro (sans appel réseau, via un test de la condition).

**Fichiers :** `tests/Feature/*` (nouveaux), `database/factories/*`. Aucun fichier `app/` modifié, sauf la correction éventuelle du test en échec.
**Risque :** nul en prod (tests uniquement).
**Validation :** suite verte en local, liste des comportements figés relue par toi.

---

## Lot 2 — Sécurisation du modèle `Folder` et règles d'accès centralisées

**Objectif :** introduire la notion d'origine et une règle d'accès unique, **sans aucun changement visible** pour les agences ni pour l'équipe.

### 2.1 Base de données (migration additive)
`folders` :
- `origin` `varchar(16)` : `b2b` | `internal`.
- `client_agency_id` nullable → `agencies` (`nullOnDelete`).

Migration en trois temps :
1. Ajout de la colonne `origin`, nullable, sans défaut.
2. **Classement des dossiers existants** : tous les dossiers existants deviennent `b2b`. Leur visibilité ne dépend que d'`agency_id`, qui n'est pas touché : la visibilité actuelle est donc strictement conservée, y compris pour un éventuel dossier sans agence (toujours invisible aux agences). Une commande `folders:origin-report` (lecture seule) affiche avant et après les comptes par origine et le nombre de dossiers sans agence.
3. Passage de `origin` en **NOT NULL sans valeur par défaut** : toute insertion qui oublie l'origine échoue, au lieu de créer silencieusement un dossier visible.

Contrainte sur MySQL/MariaDB uniquement (ignorée sous SQLite, doublée par la validation applicative) :
`CHECK (origin <> 'internal' OR (agency_id IS NULL AND main_seller_id IS NULL))`.
Ainsi, un dossier interne ne peut physiquement pas porter d'agence propriétaire ni de vendeur agence.

### 2.2 Modèle et création explicite
- `Folder::booted()` → `creating` : si `origin` est vide, **exception** (pas de valeur par défaut implicite).
- Points de création existants mis à jour pour passer `origin = b2b` explicitement : `AgencyFolderResource\Pages\CreateAgencyFolder`, `Agency\Pages\Contact:140`, `Agency\Pages\ViewProduct:458`, `Folders\Pages\CreateFolder` (admin, B2B).
- `saving` : si `origin = internal`, rejet si `agency_id` ou `main_seller_id` est renseigné ; `origin` n'est plus modifiable après création, sauf par une action dédiée réservée au super admin, historisée, avec double confirmation.

### 2.3 Règle d'accès unique
Nouvelle classe `app/Support/FolderAccess.php` :
- `scopeForAgency(Builder, User)` : `origin = 'b2b' AND agency_id = user.agency_id`.
- `agencyMayView(User, Folder)`, `agencyMayEdit(User, Folder)`, `agencyMayDelete(User, Folder)` (brouillon uniquement, comme aujourd'hui).
- `staffMayView(User, Folder)` : reprend la logique des segments, plus la règle « tous les commerciaux voient les internes » (lot 3).

Branchée dans :
- `app/Policies/FolderPolicy.php`, `FolderItemPolicy.php`, `FolderPassengerPolicy.php` ;
- `app/Filament/Agency/Resources/AgencyFolderResource.php` (`getEloquentQuery` et les `canEdit`/`canDelete`, lignes 55, 85, 91, 112) ;
- `app/Filament/Agency/Pages/Contact.php` (lignes 91, 123) et `ViewProduct.php` (lignes 339, 857) ;
- `app/Http/Controllers/FolderDriveFileController.php` (`mayAccess` : suppression du contournement `agency_id` direct, remplacé par la policy) ;
- `app/Livewire/FolderChat.php` (déjà sur la policy ; ajout du refus explicite pour les internes) ;
- routes PDF de `routes/web.php` (déjà réservées au guard `web` : inchangées, mais couvertes par les tests).

### 2.4 Tests du lot
- Tous les tests du lot 1 restent verts (aucune régression B2B).
- Un dossier `internal` avec `client_agency_id = A` : refusé à l'agence A sur liste, URL d'édition directe, prestations, voyageurs, fichiers Drive (deux routes), chat, Contact, ViewProduct, recherche globale Filament.
- Création sans `origin` → exception ; dossier interne avec `agency_id` → rejet (applicatif) et violation de contrainte (test MySQL dédié, exécuté hors SQLite).
- Migration : sur une copie de la base de prod (dump restauré en local), nombre de dossiers visibles par chaque agence identique avant et après.

**Risques :** moyen. Un point de création oublié lèverait une exception au lieu de créer le dossier, ce qui est bloquant mais pas silencieux ; c'est atténué par le `grep` exhaustif ci-dessus et les tests de parcours. La contrainte CHECK dépend de la version MySQL de prod (voir 0.1).
**Validation :** suite verte ; rapport `folders:origin-report` identique avant/après sur la copie de prod ; recette manuelle d'un compte agence de test.

---

## Lot 3 — Création des dossiers internes et interface commerciale

> **Révisé le 2026-11-07** : la préparation puis la commande explicite des prestations, ainsi que le nouveau lien entre statuts, sont décrits dans `docs/LOT3-preparation-commande.md`. Ce document **remplace** ici le mapping « `ops_status` → `status` » et la règle Calendar/guides au niveau du dossier : le contrôle se fait désormais par prestation. Les prestations préparées sont stockées dans `folder_planned_items` (option B, version 2 du document), puis converties en `folder_items` à la commande. Le lot est découpé en 3a (socle) et 3b (préparation et commande).

**Objectif :** permettre aux commerciaux de créer et suivre des dossiers internes dans `/admin`, sans toucher au parcours B2B.

### 3.1 Base de données (additive)
`folders` : `kind` (`full_trip` | `services_only`, nullable pour le B2B), `owner_user_id` → users (commercial responsable), `created_by_user_id`, `client_contact_name`, `client_contact_email`, `client_contact_phone`, `internal_notes` (texte), `ops_status` (nullable, utilisé seulement pour les internes), `lock_version` (entier, défaut 0).

`folder_tasks` (préparation de la séparation automatique/manuel) : `source` (`auto` | `manual`, défaut `auto` pour l'existant), `title`, `assigned_user_id`, `due_date`, `priority`, `status`. Les tâches automatiques gardent leur `action_code` ; le générateur ne lit et ne modifie **que** `source = auto`. L'interface des tâches manuelles arrive en lot 7, mais la colonne est posée dès maintenant pour éviter toute collision.

### 3.2 Permissions et profils
`app/Support/PermissionCatalog.php` : `folder.internal.viewAny`, `folder.internal.view`, `folder.internal.create`, `folder.internal.update`, `folder.internal.delete`, `folder.internal.reassign`, et dès maintenant `folder.finance.view` et `folder.sensitive.view` (utilisées aux lots 4 et 6).
Les commerciaux (FIT, Groupe) et la Billetterie reçoivent `folder.internal.*` **indépendamment de leur segment**, comme tu l'as décidé. Migration de données sur `roles.permissions` : ajout seulement, aucun retrait.

### 3.3 Interface (panel `/admin`)
- Liste « Dossiers » : deux boutons de création distincts, **« Nouveau dossier interne »** et **« Nouveau dossier agence (B2B) »**. Chacun ouvre son propre formulaire, avec `origin` fixé côté serveur (jamais par un champ du formulaire).
- Filtres : origine, type, commercial responsable, agence (propriétaire ou informative), période, statut ; recherche sur la référence, l'intitulé, le contact et le nom du voyageur.
- **Création rapide interne** : seuls le type et l'intitulé sont obligatoires. Agence informative facultative (`client_agency_id`), avec création de fiche agence à la volée : simple `Agency::create`, **aucun utilisateur créé**, test dédié.
- **Fiche dossier interne** : on réutilise `FolderResource` en conditionnant les sections. Masquées pour les internes : « Agence émettrice », « Vendeur principal », chat agence, « Documents partagés avec l'agence ». Affichées : responsable, contact client, notes internes, `ops_status` (avec action « Lancer les réservations » confirmée), voyageurs, vol (texte existant en attendant le lot 5), prestations (même répéteur et même calcul de prix), prestations internes, historique, vue Jour par jour.
- En-tête collant : badge « INTERNE » bien visible, responsable, `ops_status`.
- Navigation commerciale : groupe de menu « Commercial » (Dossiers, Agences, puis Calendrier et Tâches aux lots suivants), filtré par permission comme aujourd'hui.

### 3.4 Automatismes adaptés (voir le tableau d'analyse)
- `Folder::syncTechnicalStatus()` : dérive `status` d'`ops_status` pour les internes (mapping unique).
- `FolderObserver::updated` : notification « confirmé » au `owner_user_id` pour les internes.
- `FolderItem::syncGoogleCalendar` : pas de synchro pour un interne non opérationnel.
- `ActionBoardTaskGenerator` : règle « nouveau dossier » limitée aux B2B.
- `routes/console.php` : cron de clôture limité à `origin = b2b` ; règle interne séparée.
- `resources/views/pdf/recapitulatif.blade.php` : tolérance à `agency = null`.

### 3.5 Modifications simultanées
- `lock_version` est incrémenté à chaque enregistrement du dossier (y compris quand seul le répéteur des prestations change).
- `EditFolder` : la version chargée est conservée dans un champ caché ; à l'enregistrement, `UPDATE … WHERE id = ? AND lock_version = ?`. Si 0 ligne est modifiée, l'enregistrement est refusé avec un message « Le dossier a été modifié par X à HH:MM » et un bouton pour recharger. Rien n'est écrasé en silence.
- Le panel agence en bénéficie aussi (même modèle), sans changement visible tant qu'il n'y a pas de conflit.

### 3.6 Tests du lot
1. Un commercial FIT voit et modifie un dossier interne créé par un commercial Groupe.
2. Un compte agence ne voit jamais un dossier interne lié à son agence, sur toutes les surfaces listées en 2.4.
3. Une fiche agence créée depuis un dossier n'a aucun utilisateur.
4. Un dossier interne en « En préparation » : aucun job guide, aucun événement Calendar, aucune tâche « nouveau dossier ». Le même dossier en « Réservations en cours » : `SyncGuideMission` mis en file pour les prestations guides.
5. Le cron ne clôture pas un brouillon interne daté dans le passé.
6. Deux sessions éditent le même dossier : la seconde est refusée et rien n'est perdu.
7. L'historique enregistre la création, le changement de responsable et le changement d'`ops_status` avec leur auteur.
8. Toute la suite B2B du lot 1 reste verte, et les prix sont identiques.

**Risques :** moyen. `FolderResource` est volumineux (2 200 lignes) : les conditions de visibilité des sections doivent être ciblées. Le verrou optimiste touche aussi l'édition B2B (risque de faux conflits si un job modifie le dossier en arrière-plan : `recalculateBillableTotal` utilise `saveQuietly`, donc il faut l'exclure de l'incrément, ce que vérifie un test dédié).
**Validation :** recette avec un commercial sur une copie de la base ; suite verte ; aucun écart sur le parcours agence.

---

## Stratégie retenue pour les lots suivants (non développés maintenant)

### Données voyageurs sensibles (lot 4, avant mise en service des données santé)
- **Nouvelles données** : `dietary_restrictions`, `mobility_concerns` et le futur `special_needs` en cast `encrypted` (clé `APP_KEY` ; sauvegarde de la clé documentée, car sa perte rend les données illisibles).
- **Migration non destructive** : commande `passengers:encrypt-sensitive --dry-run`, puis exécution réelle. Chiffrement ligne par ligne, idempotent, après sauvegarde. Aucune donnée supprimée.
- **Historique existant** : `folder_histories.changes_payload` contient les anciennes valeurs en clair [C]. Proposition : commande qui remplace ces valeurs par « [donnée sensible modifiée] » **dans une copie de travail**, avec export chiffré des originaux conservé hors base tant que tu n'as pas décidé de la durée de conservation. À valider avant exécution, car c'est une transformation de données historiques.
- **Nouveaux journaux** : `FolderPassenger` journalise « champ sensible modifié » sans valeur ; aucun `Log::` avec ces champs.
- **Accès** : tous les commerciaux voient le dossier ; les champs santé/alimentation ne sont visibles et modifiables qu'avec `folder.sensitive.view`, sinon « Information restreinte ». Le panel agence garde l'accès à ses propres voyageurs, comme aujourd'hui.
- **2FA obligatoire pour le staff** avant ce lot (Filament 5 propose une authentification multifacteur intégrée [H, à confirmer sur la version installée]).

### Finances (lot 6)
- Service `FolderFinanceSummary`, **séparé** de `Folder::recalculateBillableTotal()` (facturation B2B inchangée).
- Postes : prestations (y compris internes masquées), hébergements, frais/ajustements.
- Prestation annulée : vente exclue, **coût conservé s'il a un montant non remboursable** (nouveau champ `non_refundable_cost`, sinon exclue).
- Ventes retenues : si un **prix global manuel** est saisi, il **remplace** la somme des ventes détaillées (jamais additionné) ; l'écart avec le détail est affiché à titre d'information. Sinon, somme des ventes détaillées + frais − remises.
- Coûts retenus : toujours la somme des coûts détaillés (le prix global ne porte jamais de coût).
- Montant manquant (`NULL`) ≠ 0 : la marge est marquée « incomplète » avec la liste des postes manquants.
- Montants : entier + `currency` (`JPY` par défaut) sur chaque nouveau poste ; un taux de conversion pourra être ajouté par poste plus tard.

### Compatibilité avec le futur module de devis
Les postes financiers (prestations, hébergements, frais) auront un `quote_line_id` nullable ajouté au moment du module devis. Le dossier conservera `sale_total_manual` et recevra plus tard un `accepted_quote_version_id`. Les tables `quotes`, `quote_versions`, `quote_lines`, `documents` (PDF émis, figés) s'ajouteront sans modifier les lots 1 à 6. Le moteur de chiffrage commun B2B/B2C justifiera alors l'extraction du calcul tarifaire, protégée par les tests du lot 1.

---

## Ordre et dépendances

```
Lot 0 (alignement + restauration) → Lot 1 (tests de référence) → Lot 2 (origine + accès) → Lot 3 (dossiers internes)
                                                                     └→ 2FA staff → Lot 4 (données sensibles)
Lot 3 → Lot 5 (vols) → Lot 6 (hébergements + finances) → Lot 7 (tâches manuelles, tableau de bord) → Lot 8 (calendrier)
```

Chaque lot : branche dédiée, suite verte, sauvegarde + archive avant déploiement, déploiement seulement après ton accord.

## Points à confirmer
1. Historique des données sensibles : accord pour remplacer les anciennes valeurs dans `folder_histories`, avec export chiffré conservé ? (sinon, on se limite aux nouvelles écritures.)
