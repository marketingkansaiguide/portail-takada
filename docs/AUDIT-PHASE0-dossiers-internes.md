# Audit Phase 0 — Dossiers internes, commerciaux et remplacement d'Ezus

Date : 2026-11-07 · Périmètre : dépôt local `portail-takada` (branche `master`, commit `e34008d`), lecture seule.
Légende : **[C]** confirmé dans le code · **[H]** hypothèse à vérifier.

> Limite : la vérification en lecture seule de la production (versions, volumétrie, écarts de fichiers) n'a pas pu être faite, l'accès SSH ayant été refusé par les garde-fous de la session. Tout ce qui suit décrit le code local. Les chiffres de prod (nombre de dossiers par segment, agences sans compte, etc.) restent à relever.

---

## 0. Constat principal

Le cahier des charges décrit un module « à ajouter ». En réalité **le portail contient déjà l'essentiel du socle** : une entité `Folder` (dossier) partagée par les agences et l'équipe interne, avec voyageurs, prestations commandées depuis le catalogue, prix d'achat/vente, marge, statuts de prestation configurables, historique, tâches, messagerie, documents Drive, vue Jour par jour, profils et permissions. **Il n'existe aucun paiement en ligne.**

La bonne stratégie n'est donc pas de créer un module parallèle, mais **d'étendre `Folder`** et l'espace admin existant, en corrigeant d'abord un point de sécurité structurel (§3.1).

---

## 1. Diagnostic technique (cartographie)

### 1.1 Socle
| Élément | Constat |
|---|---|
| Framework | **[C]** Laravel `^13.8`, PHP `^8.3` (composer.json). Version PHP de prod non relevée **[H]**. |
| UI | **[C]** Filament `^5.6` (Livewire), Tailwind 4, Vite 8. Pas de SPA, pas d'API front. |
| Autres paquets | **[C]** dompdf (PDF), maatwebsite/excel, google/apiclient (Drive, Calendar), doctrine/dbal. Pas de Spatie Permission, pas de Stripe/caisse. |
| Panels | **[C]** `admin` (path `/admin`, guard `web`) et `agency` (path racine `/`, guard `agency`), même table `users`. |
| Routes hors Filament | **[C]** `routes/web.php` : PDF récapitulatif, fax, étiquettes, fichiers Drive (`auth:web,agency`), contact, langue. `routes/api.php` : 1 webhook statut mission guide (throttle + auth privée). |
| Tâches planifiées | **[C]** clôture auto des dossiers après `end_date`, cartes IC (8h), sauvegarde BDD → Drive (6h), monitoring (15 min), génération du tableau d'actions (10 min). |
| Queues | **[C]** Jobs `SyncGuideMission` / `CancelGuideMission` (dispatch `afterCommit`). Driver de queue prod non vérifié **[H]**. |
| Emails | **[C]** 7 Mailables (validation dossier, chat agence/admin, alerte cartes IC, contact, notification portail). Notifications base de données Filament. |
| Tests | **[C]** Pest 4 : 5 fichiers Feature (frontières de permissions, intégration guides, SEO, transports). Couverture faible des parcours commande. |
| Déploiement | **[C]** pas de CI dans le dépôt ; déploiement manuel sur le serveur (mémoire projet). Fichiers de prod divergents du local (signalé par la mémoire projet, non mesuré ici). |

### 1.2 Authentification et droits
- **[C]** `User::canAccessPanel` : panel admin si un profil `is_staff`, panel agence si rôle `agency`. Cloisonnement par panel + guard de session séparé.
- **[C]** Profils multiples (`role_user`) + `users.role` principal ; `roles.permissions` JSON ; `hasPermission()` = union ; super_admin passe tout. Catalogue des permissions dans `app/Support/PermissionCatalog.php`.
- **[C]** Profils métiers existants (cf. `PLAN-droits.md`) : Marketing/IT, Billetterie, Commercial FIT, Commercial Groupe, Direction, admin, agent, super_admin, agency.
- **[C]** Périmètre de données par **segment** de dossier (`fit`, `groupe`, `billetterie`) via `Folder::scopeVisibleTo()` et permissions `folder.segment.*`.
- **[C]** Pas de 2FA, pas de vérification e-mail, login Filament standard.
- **[C]** Restes de rôles codés en dur : `FolderObserver::created` notifie `role in (admin, super_admin)` ; quelques autres listés dans `PLAN-droits.md`.

### 1.3 Dossiers et commandes (cœur métier)
| Table / modèle | Contenu existant |
|---|---|
| `folders` / `Folder` | **[C]** `reference` unique `TKD-AAAAMM-XXXX`, `folder_name`, `agency_id`, `main_seller_id`, `segment`, `status`, pax adultes/enfants, `start_date`/`end_date`, `flight_info` (**texte libre**), premier hôtel (nom, adresse, check-in, Maps), mode d'envoi des billets, téléphones, `folder_fee`, `total_price`, documents Drive. |
| `folder_items` / `FolderItem` | **[C]** prestation = produit + option + options sélectionnées + date + pax + **prix d'achat et de vente unitaires/totaux figés (entiers ¥)**, fournisseur, statut (`item_status_id`), `is_internal` (prestation masquée à l'agence), facture reçue, synchro guide, événement Google Calendar, champs personnalisés. |
| `item_statuses` | **[C]** statuts de prestation configurables en admin (nom + couleur). Les statuts « non facturables » excluent la ligne du total. |
| `folder_passengers` | **[C]** prénom, nom, date de naissance, nationalité, restrictions alimentaires, mobilité. Historisés. |
| `folder_histories` | **[C]** journal consolidé avec acteur (`resolveActorId` multi-guard) et payload structuré. |
| `folder_tasks` | **[C]** tâches **générées automatiquement** (`ActionBoardTaskGenerator`) avec `action_code`, priorité déduite ; pas de responsable ni d'échéance manuelle. |
| `folder_messages` | **[C]** chat agence ↔ Takada, pièces jointes, « action requise ». |
| `hotels` | **[C]** référentiel hôtels (nom, téléphone…) utilisé pour le premier hébergement / check-ins. Pas de réservations hôtelières par dossier. |
| `agencies` | **[C]** fiche agence (groupe client, contact, `is_approved`) ; les comptes sont des `users.agency_id`. Une agence peut exister sans compte. |

- **[C]** Statuts de dossier : `draft`, `pending` (en attente de validation), `confirmed`, `completed`, `cancelled`. Le cron passe en `completed` après `end_date`.
- **[C]** Cycle B2B : l'agence crée un dossier dans son panel, ajoute des prestations du catalogue (prix calculés par période/tranche/options, filtrés par groupe client), puis **valide** → email aux admins → l'équipe traite (fax/email fournisseur, mission guide, statut de prestation). **Aucun paiement en ligne** : la facturation passe par un récapitulatif / pré-facture PDF.
- **[C]** Côté admin, `FolderResource` (2 200 lignes) offre déjà : infos générales, premier hôtel, voyageurs, vol & séjour (texte), statut & facturation avec **marge calculée** (ventes + frais − achats), documents, prestations commandées (même logique tarifaire que côté agence), **prestations internes masquées**, historique, vue Jour par jour, en-tête collant.
- **[C]** Le formulaire admin impose `agency_id` **obligatoire**.
- **[C]** Monnaie unique : yen, montants entiers. Aucune notion de devise ni de taxe.

### 1.4 Dépendances implicites repérées
- **[C]** `agency_id` sert **à la fois** de lien commercial et de **droit d'accès** : `FolderPolicy::view`, `AgencyFolderResource::getEloquentQuery`, `FolderDriveFileController::mayAccess` donnent l'accès à tout dossier dont `agency_id` = agence de l'utilisateur.
- **[C]** `main_seller_id` est alimenté par les utilisateurs **de l'agence** (vendeur côté agence), pas par un commercial Takada.
- **[C]** `Folder::total_price` est recalculé depuis les prestations non internes + frais (montant facturé à l'agence).
- **[C]** Les observers `FolderItem` déclenchent emails fournisseurs, synchro guide et Calendar selon le statut du dossier (`confirmed`/`completed`) : passer un dossier interne en `confirmed` déclenche les mêmes automatismes opérationnels (souhaitable, à garder en tête).

---

## 2. Composants réutilisables

| Besoin du cahier | Existant réutilisable | Écart |
|---|---|---|
| Comptes commerciaux, rôles | Profils + permissions + `is_staff` | Ajouter permissions dédiées ; 2FA absente |
| Interface interne | Panel `admin` Filament | Navigation à recentrer sur les commerciaux |
| Dossier, n° unique, statut, notes | `Folder` + `reference` | Type A/B, commercial Takada responsable, contact client, notes internes, statuts plus fins |
| Voyageurs | `FolderPassenger` | Téléphone, voyageur principal, besoins particuliers |
| Vols | `folders.flight_info` (texte) | Segments structurés + fuseaux |
| Hébergements | 1er hôtel sur le dossier + référentiel `hotels` | Table de réservations hôtelières par dossier |
| Commande catalogue sans paiement | Répéteur `folderItems` admin (même moteur tarifaire, prix figés) | **Déjà sans paiement** ; ajouter traçabilité « origine interne » |
| Statuts de prestation | `item_statuses` configurables | Distinguer « commandé / transmis / confirmé fournisseur » dans la liste |
| Historique | `FolderHistory` | Étendre aux nouvelles entités ; exclure données sensibles |
| Tâches | `FolderTask` + générateur | Tâches manuelles (responsable, échéance) |
| Tableau de bord | `ActionBoardWidget`, `UpcomingCheckins`, stats | Vue « commercial » |
| Calendrier | `GoogleCalendarWidget`, vue Jour par jour | Calendrier interne multi-dossiers |
| Finances | PA/PV par prestation + marge dossier | Postes hôtels / frais / remises, prix global manuel, statut « incomplet » |
| PDF | dompdf + `pdf.recapitulatif` | Futur moteur de devis |
| Guides | Intégration WordPress existante (jobs + webhook) | Rien à faire en phase 1 |

---

## 3. Risques et limitations

1. **Fuite vers les agences via `agency_id` (critique)** [C]. Associer « à titre informatif » une agence à un dossier interne en remplissant `agency_id` le rendrait **immédiatement visible et modifiable** par tous les comptes de cette agence (liste, édition, fichiers, chat, PDF). Il faut un champ distinct ou un drapeau d'origine contrôlé dans **toutes** les vérifications (policy, query agence, contrôleur de fichiers, chat, emails).
2. **`agency_id` obligatoire** dans le formulaire admin [C] : impossible aujourd'hui de créer un dossier sans agence (client direct B2C).
3. **`FolderResource` monolithique** (2 200 lignes) et `AgencyFolderResource` (1 860 lignes) dupliquent la logique tarifaire [C] : toute modification du moteur de prix est risquée pour les deux portails. Pas de service tarifaire isolé.
4. **Peu de tests** sur le parcours commande B2B [C] : aucune protection automatique contre les régressions.
5. **Divergence prod/local** [H, signalée par la mémoire projet] : il faut aligner avant tout lot structurel, sinon un déploiement peut écraser des correctifs faits en prod.
6. **Montants entiers en ¥ sans devise** [C] : suffisant tant que tout est en yen ; une colonne devise devra être ajoutée avant d'accepter des coûts en € ou $.
7. **Notifications par rôle codé en dur** (`admin`, `super_admin`) [C] : les commerciaux ne recevront pas les alertes sans adaptation.
8. **Données sensibles** (allergies, mobilité, dates de naissance) déjà stockées en clair et **copiées dans l'historique** [C] (`FolderPassenger` journalise `dietary_restrictions`, `mobility_concerns`).
9. **Pas de verrou optimiste** [C] : deux commerciaux sur le même dossier peuvent s'écraser (le répéteur Filament réécrit toutes les lignes).
10. **Cron de clôture** [C] passe automatiquement en `completed` des dossiers internes ; à garder ou à restreindre selon le nouveau cycle de statuts.

---

## 4. Architecture recommandée

- **Une seule application, un seul panel interne** : on garde le panel `admin` (`/admin`) comme interface interne. Le cloisonnement réel est déjà assuré par guard + `canAccessPanel` + policies. Un sous-domaine `interne.takada-travel.com` n'apporte pas de sécurité supplémentaire ; il peut être ajouté plus tard comme simple alias (`->domain()` Filament) si souhaité pour l'ergonomie. **Recommandation : pas de sous-domaine en phase 1.**
- **Un seul modèle `Folder`** avec une notion d'**origine** : `origin = agency | internal`. Les dossiers internes ne sont jamais servis au panel agence, quelle que soit l'agence liée.
- **Séparer lien commercial et accès** : nouveau champ `client_agency_id` (agence informative, nullable) ; `agency_id` reste le champ « propriétaire B2B » et **reste NULL pour les dossiers internes**. Les règles d'accès agence ne changent pas, et un dossier interne ne peut donc jamais matcher.
- **Commercial Takada responsable** : nouveau champ `owner_user_id` (staff), distinct de `main_seller_id` (vendeur agence). Tous les commerciaux voient tout (règle du cahier), via la permission existante `folder.segment.all` ou un nouveau segment.
- **Pas de second moteur de billetterie** : les prestations internes utilisent `folder_items` et le même répéteur/moteur de prix. Étape préalable recommandée : extraire le calcul tarifaire dans un service partagé (`ProductPricingService`) couvert par des tests, sans changer le comportement.
- **« Sans paiement »** : rien à désactiver (aucun paiement n'existe). On trace l'origine (`origin=internal`, `created_by`) pour distinguer une commande interne ; si un jour un paiement est introduit, on ajoutera un `payment_requirement` explicite.
- **Préparation devis (phase 4)** : ne rien créer maintenant ; s'assurer que les postes financiers (prestations, hébergements, frais) ont tous `purchase_amount`, `sale_amount`, `currency`, et une colonne nullable `quote_line_id` ajoutable plus tard. Le devis accepté générera/actualisera ces postes ; le dossier gardera un `sale_total_manual` tant que le devis n'existe pas.

---

## 5. Structure de données proposée (ajouts non destructifs)

**`folders`** (colonnes ajoutées, toutes nullables ou avec défaut) :
`origin` (`agency` par défaut pour l'existant), `kind` (`full_trip` | `services_only`), `owner_user_id` → users, `created_by_user_id`, `client_agency_id` → agencies, `client_contact_name/email/phone`, `internal_notes`, `ops_status` (cycle interne, distinct de `status` B2B), `sale_total_manual` + `currency`, `lock_version` (verrou optimiste).

**`folder_passengers`** : `phone`, `is_main_contact`, `special_needs` (texte). Les champs sensibles passent en cast `encrypted` et sont masqués dans l'historique (« modifié » sans valeur).

**`folder_flights`** (nouvelle) : `folder_id`, `direction` (aller/retour/intérieur/correspondance), `airline`, `flight_number`, `from_airport`, `to_airport`, `departs_at_local` + `departs_tz`, `arrives_at_local` + `arrives_tz`, `status`, `booking_ref`, `notes`, `sort_order`. `flight_info` existant conservé et affiché tant qu'il n'est pas migré.

**`folder_accommodations`** (nouvelle) : `folder_id`, `hotel_id` (référentiel, nullable) + `hotel_name` libre, `city`, `check_in`, `check_out` (nuits calculées), `rooms` (JSON type/nombre/occupants), `meal_plan`, `supplier_id`, `confirmation_number`, `booking_status_id` → nouvelle table `booking_statuses` configurable (À réserver, Demande envoyée, En attente, Confirmé, Annulé), `purchase_amount`, `sale_amount`, `currency`, `notes`. Le « premier hôtel » actuel est dérivé de la première ligne (compatibilité étiquettes/check-ins).

**`folder_charges`** (nouvelle, petite) : frais annexes, transports hors catalogue, remises/ajustements (`type`, `label`, `purchase_amount`, `sale_amount`, `currency`).

**`folder_tasks`** (colonnes ajoutées) : `title`, `assigned_user_id`, `due_date`, `priority`, `status`, `is_manual`. Les tâches automatiques existantes restent inchangées.

**Finances (calcul, pas de table)** : service `FolderFinanceSummary` = Σ prestations non annulées (PA/PV) + hébergements + charges. Si `sale_total_manual` est renseigné, il prime comme « ventes retenues » et l'écart avec le détail est affiché. Un poste sans PA → marge marquée **incomplète** (jamais 0). Pas de double comptage : une prestation catalogue n'existe qu'une fois (`folder_items`).

**Historique** : réutiliser `FolderHistory` pour les nouvelles entités (même `logConsolidated`).

---

## 6. Séparation portail interne / portail partenaire et sécurité

1. Dossier interne = `origin = internal` et `agency_id = NULL` → invisible par construction pour le panel agence.
2. Défense en profondeur : `FolderPolicy` / `FolderItemPolicy` / `FolderDriveFileController` / `FolderChat` / emails agence refusent explicitement tout dossier `origin = internal` pour un compte `agency`.
3. Les fiches agence créées par un commercial n'ont aucun utilisateur ; la création d'un compte reste une action admin séparée (déjà le cas).
4. Permissions nouvelles dans `PermissionCatalog` : `folder.internal.*`, `folder.finance.view`, `folder.sensitive.view`.
5. 2FA : Filament 5 propose une authentification multifacteur intégrée (TOTP / e-mail) **[H, à confirmer sur la version installée]** ; recommandée pour les comptes staff.
6. Tests IDOR systématiques (accès direct par ID aux routes PDF, fichiers, édition).

---

## 7. Plan de développement par étapes testables

| # | Lot | Contenu | Risque | Validation |
|---|---|---|---|---|
| 0 | Alignement prod/local + filet de tests | Diff prod/local, rapatrier les écarts ; tests Pest sur le parcours agence (création, prestations, validation, accès croisé) | Faible | Tests verts sur copie de prod |
| 1 | Socle dossier interne | Colonnes `origin`, `kind`, `owner_user_id`, `client_agency_id`, contact, notes, `lock_version` ; `agency_id` facultatif pour les internes ; garde-fous policies ; fiche agence créable depuis le dossier | **Moyen** (accès) | Tests : agence liée ne voit jamais le dossier interne ; fiche agence sans compte |
| 2 | Voyageurs étendus + données sensibles | Champs ajoutés, chiffrement, historique expurgé | Faible | Tests de non-divulgation |
| 3 | Vols structurés | Table + onglet, fuseaux | Faible | Saisie/affichage, Jour par jour |
| 4 | Hébergements | Table + statuts configurables + compatibilité « premier hôtel » | Moyen (check-ins, étiquettes) | Étiquettes et check-ins inchangés |
| 5 | Prestations internes depuis le catalogue | Réutiliser le répéteur ; extraction préalable du moteur de prix en service testé | **Élevé** (code partagé) | Tests de prix identiques avant/après |
| 6 | Finances | Charges, prix global manuel, synthèse, marge incomplète | Moyen | Tests anti-double comptage |
| 7 | Tâches manuelles + tableau de bord commercial | Responsable/échéance ; widgets filtrés | Faible | — |
| 8 | Calendrier interne | Vue mois/semaine/liste, lien vers dossier | Faible | Perf. avec volumétrie prod |
| 9 | 2FA staff | Activation MFA | Faible | — |
| — | Phases 4-5 (devis, PDF, Ezus, stats) | Étude fonctionnelle préalable, rien codé | — | Validation spécifique |

Chaque lot : migration non destructive, sauvegarde BDD avant déploiement (la sauvegarde Drive 6 h existe déjà), retour arrière = `migrate:rollback` du lot + revert git.

---

## 8. Interface interne proposée

- **Menu** (panel admin, filtré par profil) : Tableau de bord · Dossiers · Calendrier · Tâches · Catalogue · Agences · Fournisseurs/Hôtels · Opérations (existant) · Paramètres.
- **Liste des dossiers** : recherche globale (réf., nom, voyageur, agence), filtres commercial / agence / période / statut / type / origine.
- **Fiche dossier** : en-tête collant existant (réf., statut, dates, pax, marge) + onglets **Vue d'ensemble · Voyageurs · Vols · Hébergements · Prestations · Tâches · Finances · Historique** (+ Messages/Documents pour les dossiers agence). La vue Jour par jour actuelle devient la vue d'ensemble.
- **Création rapide** : seuls type + intitulé obligatoires ; tout le reste complétable ensuite.

---

## 9. Arbitrages à valider avant de coder

1. **Ce qui existe déjà suffit-il comme base ?** Confirmer qu'on étend « Dossiers Clients » (panel `/admin`) plutôt que de créer un espace séparé. *Recommandé : oui.*
2. **Sous-domaine `interne.takada-travel.com`** : non en phase 1 (aucun gain de sécurité), alias possible plus tard ? *Recommandé : non pour l'instant.*
3. **Dossiers internes et segments** : un commercial FIT doit-il voir les dossiers Groupe et les dossiers créés par les agences (le cahier dit « tous les dossiers internes ») ? Les profils actuels filtrent par segment.
4. **Une agence peut-elle voir un dossier créé par Takada pour elle ?** Le cahier dit jamais. Aujourd'hui, l'équipe crée parfois des dossiers *pour* une agence (agence émettrice obligatoire) qui les voit : faut-il conserver ce cas (dossier « agence ») à côté des dossiers « internes » ?
5. **Cycle de statuts interne** : valider la liste (Brouillon, En préparation, Réservations en cours, Prêt au départ, En voyage, Terminé, Annulé) et si le cron de clôture automatique s'applique aux internes.
6. **Devises et taxes** : tout reste-t-il en yen TTC en phase 1 ? (sinon il faut une colonne devise dès le lot 4.)
7. **Données sensibles** : durée de conservation des données voyageurs après le voyage, et qui peut voir allergies/santé.
8. **2FA obligatoire** pour les comptes staff ?
9. **Alignement prod/local** : accord pour un diff en lecture seule de la prod en début de lot 0 (non réalisé dans cet audit).
