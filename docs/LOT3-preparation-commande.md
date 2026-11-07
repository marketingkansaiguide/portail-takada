# Lot 3b — Préparation puis commande explicite des prestations internes

Version 2 · 2026-11-07 · Remplace la version 1 de ce document.
Légende : **[C]** constaté dans le code ou en prod · **[H]** hypothèse.
Statut : **recommandation à valider**. Rien n'est implémenté.

> **Correction de la version 1.** J'y affirmais que le formulaire de prestation utilisait « 129 fois `$record` », c'est-à-dire un `FolderItem` réel. C'était faux : ma recherche comptait tous les `->` du fichier. Après une vérification ligne par ligne, le schéma de la fiche prestation (`FolderResource`, lignes 955 à 2045) n'utilise `$record` qu'**une seule fois** (affichage des fichiers joints d'un champ personnalisé). Tout le reste travaille sur l'**état du formulaire** (`$get`/`$set`). Ce fait change la conclusion : la réutilisation du formulaire, qui était mon principal argument pour l'option A, vaut aussi pour l'option B.

---

## 1. Comparaison technique A / B

### 1.1 Ce qui lit ou déclenche des choses sur `folder_items` [C]
| Famille | Fichiers | Lecture des prestations |
|---|---|---|
| Modèle et hooks | `FolderItem` (`creating`, `created`, `updated`), `Folder` (`folderItems`, `allFolderItems`, `internalItems`, `billableFolderItems`, `recalculateBillableTotal`), `FolderObserver` | Eloquent |
| Jobs guides | `SyncGuideMission`, `CancelGuideMission`, commande `RetryGuideMissionSync`, webhook `GuideMissionStatusController` | `FolderItem::find`, requêtes sur dossier `confirmed` |
| **Commandes en masse** | `EditAgencyFolder` (validation agence : `folderItems()->…->each(SyncGuideMission)` **pour toutes les prestations guides du dossier**), `RetryGuideMissionSync` (toutes les prestations guides d'un dossier confirmé en échec) | Eloquent, par dossier |
| Calendar | `FolderItem::syncGoogleCalendar` (appelé dans `created`/`updated`, **synchrone**) | modèle |
| Tâches | `ActionBoardTaskGenerator`, `CheckIcCardsOpening` | `allFolderItems` |
| Billetterie | `IcCards`, `IwaOrders`, `SevenTicketsOrders`, `TrainTickets`, `UpcomingCheckins`, `IwaTicketsExport` | Eloquent + `whereRaw` sur `folder_items.*` |
| Statistiques | `StatsOverview`, `FolderPerformance`, `AgencyPerformance`, `CategoryPerformance`, `ProductPerformance`, `SupplierPerformance` | Eloquent + **jointures SQL** sur `folder_items` |
| Documents | `pdf.recapitulatif`, `pdf.fax`, `pdf.labels`, `pdf.train-labels`, `pdf.seven-tickets-labels`, `emails/folder-validated` | relations et route model binding |
| Interface | `FolderResource`, `EditFolder::beforeSave` (`sumBillableFormItems`), `FolderDayByDay`, `FolderStickyHeader`, `AgencyFolderResource`, `ViewProduct` | état du formulaire + Eloquent |
| Maintenance | `MergePrivateVariant`, `PurgeProductsLot`, `MigrateFolderFilesToGoogleDrive` | `DB::table('folder_items')` |

Au total : 36 fichiers, 167 références.

### 1.2 Option A : `order_state` dans `folder_items`
Il faut qu'**aucune** des lectures ci-dessus ne voie une ligne préparée. La seule manière fiable est une portée globale Eloquent. Mais elle **ne protège pas** :
- les jointures SQL des widgets de statistiques ni les `whereRaw`/sous-requêtes construites hors du modèle ;
- les `DB::table('folder_items')` ;
- tout futur code qui appelle `withoutGlobalScopes()` (fréquent dans Filament pour les relations et les actions en masse).

Il faudrait aussi ajouter des gardes dans les trois hooks de `FolderItem`, qui sont **partagés avec le B2B**. Chaque oubli futur peut faire partir une mission guide ou un Calendar pour une prestation non commandée.

### 1.3 Option B : `folder_planned_items` → conversion en `folder_items`
Les prestations préparées vivent dans une table qu'**aucun** des 36 fichiers ne lit. La protection est **structurelle**, sans garde à poser : la validation agence, la relance des guides, les statistiques ou un futur script ne peuvent pas « voir » une prestation préparée. La conversion **crée** un `FolderItem` normal, donc le parcours opérationnel existant (`creating` → statut « En attente de validation », `created` → total, guide si dossier confirmé, Calendar, historique) s'applique tel quel. Ni `FolderItem` ni ses hooks ne changent de logique.

### 1.4 Tableau de décision
| Critère | A | B |
|---|---|---|
| Risque B2B | Hooks et requêtes partagés modifiés ; portée globale sur tout le modèle | `FolderItem` intact (une colonne nullable ajoutée) ; seul le schéma du formulaire est extrait en méthode |
| Automatismes | Protection par convention (portée + gardes), contournable | **Protection structurelle**, non contournable par une requête |
| Formulaire et tarifs | Réutilisés | Réutilisés : même schéma (état du formulaire) et même `updateItemPrices` ; 3 éléments à masquer en mode préparation (§2) |
| Maintenance | Un seul schéma | Deux tables. Atténué par une **table de correspondance unique** + un **test de parité** qui échoue dès qu'une colonne de `folder_items` n'est ni copiée ni explicitement exclue |
| Historique | Continu (même ligne) | Continu par dossier (l'historique est déjà rattaché au dossier) + liens croisés `planned_item_id` ↔ `folder_item_id` |
| Commandes partielles | Oui | Oui |
| Doublons | Gérés par l'application | **Garantis par la base** : index unique sur `folder_items.planned_item_id` (une prestation préparée ne peut produire qu'une seule commande) |
| Double comptage | Impossible (une ligne) | Impossible si la prestation préparée convertie sort du budget : son statut `converted` l'exclut du calcul |
| Devis futur | Ligne de devis → `prepared` | Ligne de devis acceptée → **prestation préparée** (`source = quote`), puis le même bouton « Commander ». C'est l'entrée naturelle demandée |

### Recommandation définitive : **option B**
Elle protège les automatismes **par construction**, laisse le modèle `FolderItem` et le parcours B2B intacts, offre une garantie anti-doublon en base, et constitue l'entrée commune pour les futurs devis B2B et B2C. Son seul vrai coût, la synchronisation de deux schémas, est maîtrisé par une correspondance unique et un test de parité.

---

## 2. Réutilisation du formulaire et du moteur tarifaire

**[C] Constats**
- Le schéma de la fiche prestation ne lit que l'état du formulaire, à trois exceptions près :
  1. `?FolderItem $record` dans l'affichage des fichiers d'un champ personnalisé (ligne 982), pour retrouver le dossier ;
  2. les actions **« Aperçu de l'email fournisseur »** et **« Fax Google Sheets »**, qui rechargent `FolderItem::find($itemData['id'])` ;
  3. l'affichage du statut fournisseur (`item_status_id`) et de la synchro guide.
- `updateItemPrices($set, $get)` (`FolderResource:159`) ne lit que `$get('product_id' | 'service_date' | 'quantity' | 'selected_options' | 'custom_values' | …)` et des modèles catalogue (`Product`, `ProductOption`, `ProductPeriod`).

**Modifications**
- Extraction **mécanique, sans changement de contenu**, du tableau `->schema([...])` du répéteur `folderItems` vers `FolderResource::folderItemSchema(string $mode = 'ordered')`. Le répéteur B2B appelle `folderItemSchema('ordered')` et obtient exactement le même schéma qu'aujourd'hui.
- En mode `planned` : les deux actions fournisseur sont masquées, le champ de statut fournisseur et le bloc de synchro guide sont masqués, et `$record` est typé `?Model` en lisant `$record?->folder` (compatible avec les deux modèles).
- Moteur tarifaire : **aucune copie**. Le même `updateItemPrices` alimente les prix prévisionnels pendant la préparation et recalcule les prix au moment de la commande (appelé avec des closures sur un tableau, comme dans les tests de caractérisation du lot 1).

---

## 3. Modèle de données

### 3.1 Nouvelle table `folder_planned_items`
- Mêmes colonnes métier que `folder_items` : `product_id`, `product_option_id`, `supplier_id`, `service_date`, `quantity`, `pax_adults`, `pax_children`, `pax_teenagers`, `unit_price`, `total_price`, `purchase_unit_price`, `purchase_total_price`, `custom_values` (JSON), `selected_options` (JSON), `title`, `is_internal`.
- Colonnes propres :
  - `folder_id` ;
  - `state` (`draft` | `converted` | `discarded`) ;
  - `source` (`manual` | `quote`, pour le futur) et `source_ref` (nullable, futur `quote_line_id`) ;
  - `notes` (opérationnelles) ;
  - `priced_at` (date du dernier calcul de prix) ;
  - `created_by_user_id`, `updated_by_user_id` ;
  - `folder_item_id` (nullable, rempli à la conversion) ;
  - `converted_at`, `converted_by_user_id`, `order_batch_id` ;
  - `lock_version` ; horodatages.
- Contraintes : `state` avec CHECK ; index `(folder_id, state)`.
- Pas d'**effacement physique** d'une prestation préparée **convertie** : elle reste comme trace. Une préparée non convertie peut être supprimée ou marquée `discarded`.

### 3.2 `folder_items` (seul ajout)
- `planned_item_id` nullable → `folder_planned_items`, **index unique** (une prestation préparée donne au plus une commande).
- `ordered_by_user_id` nullable, `order_batch_id` nullable.
- Toutes les lignes existantes ont ces colonnes à NULL : aucun effet sur le B2B.

### 3.3 Nouvelle table `folder_order_batches`
`id`, `folder_id`, `user_id`, `idempotency_key` (**unique**), `planned_item_ids` (JSON), `result` (JSON par ligne : `ok` | `already_converted` | `invalid` + motif), `snapshot` (JSON : prix prévisionnels, prix retenus, écarts, options, date, pax), `price_changes_acknowledged`, `created_at`.

### 3.4 Correspondance unique et test de parité
- `App\Support\PlannedItemMapper::toFolderItemAttributes(FolderPlannedItem): array` est le **seul** endroit qui décide de ce qui est copié.
- La liste `PlannedItemMapper::EXCLUDED_FROM_PLANNING` contient les colonnes purement opérationnelles : `item_status_id`, `invoice_received_at`, `google_calendar_event_id`, `guide_*`, `label_exported_at`.
- **Test de parité** : `FolderItem::$fillable` = colonnes copiées ∪ colonnes exclues. Si quelqu'un ajoute une colonne à `folder_items` sans décider de son sort, le test échoue.

---

## 4. Conversion transactionnelle (bouton « Commander »)

`App\Services\PlannedItemOrderService`

**`summary(Folder, ids)`** → pour chaque prestation préparée :
- contrôle de validité (produit actif, date, pax, options obligatoires, champs obligatoires du produit, disponibilité pour le groupe client si une agence informative est liée) ;
- **recalcul du tarif** par `updateItemPrices` et comparaison avec les prix préparés (achat et vente) ;
- génération d'une `idempotency_key` pour la fenêtre.

**`order(Folder, ids, User, expectedFolderLockVersion, idempotencyKey, acceptNewPrices)`** :
1. Clé d'idempotence déjà connue → on renvoie le résultat enregistré, sans rien refaire.
2. `DB::transaction` :
   - verrou sur le dossier (`lockForUpdate`) + contrôle de `lock_version` ;
   - verrou sur les prestations préparées demandées, `state = draft` seulement (les autres sont rapportées `already_converted`/`discarded`) ;
   - écart de tarif non accepté → refus avec la liste des écarts ;
   - si le dossier interne est encore `status = draft` → `confirmed` (voir §5 sur la portée exacte de ce passage) ;
   - pour chaque prestation : `FolderItem::create(PlannedItemMapper::…)` + `planned_item_id`, `ordered_by_user_id`, `order_batch_id` → **parcours `creating`/`created` existant** (statut fournisseur « En attente de validation », total, mission guide en file `afterCommit`, historique) ;
   - prestation préparée → `state = converted`, `folder_item_id`, `converted_at/by` ;
   - écriture de `folder_order_batches` et d'une entrée d'historique « Commande de N prestations par X » ;
   - incrément de `lock_version`.
3. Commit **tout ou rien**. Les lignes invalides ont déjà été retirées au récapitulatif ; si une erreur survient pendant la transaction, rien n'est enregistré et le message nomme la ligne fautive.
4. Effets externes **après commit uniquement** (guides : déjà `afterCommit` ; Calendar : voir §6).

**Protections contre les doublons, cumulées :** clé d'idempotence unique, verrou de ligne + contrôle `state = draft`, **index unique `folder_items.planned_item_id`** (dernier rempart en base), bouton désactivé après clic.

La commande **ne suppose aucune confirmation fournisseur** : la prestation entre avec le statut par défaut « En attente de validation », puis suit le cycle existant (« En cours de traitement » → « Confirmé » / « Pas de disponibilité » / « Annulé »), modifié par l'équipe comme aujourd'hui.

---

## 5. Cycles de statuts (trois axes distincts)

**1. Prestation préparée** (`folder_planned_items.state`) : `draft` (modifiable, comptée au budget prévisionnel) → `converted` (figée, liée à sa commande, sortie du budget) ou `discarded` (abandonnée, gardée pour trace).

**2. Prestation commandée** (`folder_items.item_status_id`, **existant, inchangé**) : « En attente de validation » (défaut à la création) → « En cours de traitement » → « Confirmé » | « Pas de disponibilité » | « Annulé ». Plus « En attente d'ouverture des réservations / Achat planifié » quand la réservation ne peut pas encore être ouverte.

**3. Dossier interne**
- `ops_status` (commercial, modifiable à la main, **sans aucun effet sur les prestations**) : Brouillon, En préparation, Réservations en cours, Prêt au départ, En voyage, Terminé, Annulé. « Réservations en cours » est seulement **proposé** après une première commande.
- `status` (technique, existant) : `draft` tant qu'aucune prestation n'est commandée ; `confirmed` à la **première commande** (nécessaire, puisque les missions guides n'existent que pour un dossier confirmé) ; `completed` / `cancelled` quand `ops_status` passe à Terminé / Annulé.

**Pourquoi la commande d'une prestation ne commande jamais les autres [C]**
- Les automatismes « en masse » (`EditAgencyFolder` lors d'une validation agence, `RetryGuideMissionSync`) parcourent `folder_items` : les prestations préparées n'y sont pas.
- Le passage du dossier à `confirmed` ne déclenche dans `FolderObserver` qu'une notification [C]. Aucune boucle ne commande les prestations.
- La validation agence (`EditAgencyFolder`) n'est pas accessible pour un dossier interne (panel agence fermé aux internes, lot 2).
- Test dédié : 5 préparées, commande de 2 → exactement 2 `folder_items`, 2 jobs au plus, 3 préparées intactes.

---

## 6. Modifications des automatismes (liste fermée)

| Fichier | Changement | Effet B2B |
|---|---|---|
| `FolderItem::booted` `created`/`updated` | `syncGoogleCalendar()` enveloppé dans `DB::afterCommit(...)`. Hors transaction, l'appel reste immédiat ; dans une transaction, il attend le commit. | Comportement identique (le formulaire Filament enregistre déjà dans une transaction [C], donc l'appel a lieu au commit au lieu d'avant) ; couvert par les tests de référence |
| `FolderObserver::updated` | Notification « confirmé » : destinataire = responsable interne si `origin = internal` | Aucun |
| `routes/console.php` (cron de clôture) | Limité à `origin = b2b` | Aucun |
| `ActionBoardTaskGenerator` | Règle « nouveau dossier à analyser » limitée à `origin = b2b` | Aucun |
| `FolderResource` | Extraction de `folderItemSchema($mode)` ; section « En préparation » et action « Commander » visibles **seulement** si `origin = internal` | Schéma identique en mode `ordered` |
| `pdf.recapitulatif` | Tolère `agency = null` | Rendu identique |

**Aucun changement** dans `SyncGuideMission`, `CancelGuideMission`, `RetryGuideMissionSync`, `EditAgencyFolder`, les pages Billetterie, les widgets, `AgencyFolderResource`, `ViewProduct`, ni dans la logique tarifaire.

**Modifications et annulations**
- Prestation préparée : modification ou suppression libre, sans aucun effet externe ; historique « préparation modifiée ou supprimée ».
- Prestation commandée : elle est un `FolderItem` ordinaire, donc les règles existantes s'appliquent (mise à jour de la mission guide si une donnée change, `CancelGuideMission` au passage en « Annulé », recalcul du total). Une modification **ne crée jamais** de nouvelle commande : elle met à jour la ligne existante, et l'index unique interdit une deuxième conversion. Dans l'interface interne, la suppression directe d'une prestation commandée est masquée et remplacée par l'annulation.

---

## 7. Interface (onglet Prestations d'un dossier interne)

```
┌ EN PRÉPARATION (3) ─────────── Budget prévisionnel : PA 84 000 ¥ · PV 120 000 ¥ · marge 30 % ┐
│ ☐ 12/04 Visite guidée Kyoto · 4 pax · Journée        PA 25 000  PV 38 000   ✎  🗑            │
│ ☐ 13/04 Transfert Kansai → Kyoto · 4 pax             PA 18 000  PV 24 000   ✎  🗑            │
│ ☐ 14/04 Cérémonie du thé · 4 pax   ⚠ tarif à revoir  PA 41 000  PV 58 000   ✎  🗑            │
│ [+ Préparer une prestation]              [Commander les prestations sélectionnées (2)]      │
├ COMMANDÉES (2) ────────────────────── Opérationnel : PA 30 000 ¥ · PV 41 000 ¥ · marge 27 % ┤
│ 10/04 Pass IC · 4          ● En attente de validation    commandée le 07/11 par Lucie       │
│ 11/04 Guide Osaka          ● Confirmé · mission guide ✓                                     │
├ HISTORIQUE DES COMMANDES ───────────────────────────────────────────────────────────────────┤
│ 07/11 14:02 · Lucie · 2 prestations · voir le détail ▸                                       │
└─────────────────────────────────────────────────────────────────────────────────────────────┘
```

- **Préparer** : le même formulaire de prestation qu'aujourd'hui (mode `planned`), dans un panneau latéral, avec les prix calculés en direct.
- **Commander** : fenêtre de récapitulatif listant, par prestation, la date, les options, les pax, le PA et le PV prévus face au PA et au PV actuels (écarts en orange), plus les lignes non commandables avec leur motif. Case « J'accepte les tarifs actualisés » si nécessaire. Bouton « Confirmer la commande ».
- Résultat : « 2 prestations commandées », puis un état par ligne (mission guide en file, Calendar). En cas de refus (dossier modifié entre-temps, tarif changé), un message clair et un bouton pour recharger.
- Après une commande, on continue à préparer d'autres prestations normalement.
- Dossier B2B : écran **inchangé**.

---

## 8. Finances : prévisionnel et opérationnel séparés

Service `FolderFinanceSummary` (implémentation au lot 6, structure fixée ici), **indépendant** de `Folder::recalculateBillableTotal()` (facturation B2B inchangée).

| Indicateur | Prestations préparées `draft` | Prestations commandées non annulées | Commandées annulées | Hébergements / frais |
|---|---|---|---|---|
| Coûts et ventes **prévisionnels** (budget) | prix préparés | prix retenus | coût non remboursable seulement | inclus |
| Coûts et ventes **opérationnels** | — | prix retenus | coût non remboursable seulement | inclus si confirmés |

- Une prestation préparée **convertie** n'est jamais comptée : seule sa commande l'est. Pas de double comptage.
- Marge estimée = prévisionnel ; marge opérationnelle = opérationnel. Les deux sont affichées.
- Prix global manuel (lot 6) : il remplace les **ventes** retenues, jamais les coûts, et n'est jamais additionné au détail.
- Montant `NULL` ≠ 0 : la marge est marquée « incomplète » avec la liste des postes en cause.
- Les écarts entre préparé et retenu se lisent dans `folder_order_batches.snapshot`.

---

## 9. Tests

### 9.1 Non-régression B2B (lot 1, à faire passer avant **et** après le lot 3b)
1. **Tarifs** (caractérisation, valeurs figées) : périodes, plages, tranches, pax supplémentaire, global / par personne, âges, options obligatoires et groupes, tarifs saisonniers d'options, Extras sans tranche, multiplicateur, prix d'achat (base, saisonnier, guide), groupes clients. Équivalence entre `updateItemPrices` admin et agence.
2. **Parcours agence** : connexion, catalogue public et privé, ajout au dossier depuis `ViewProduct`, création, modification, suppression en brouillon, validation (`pending` → email `FolderValidatedMail`, puis confirmation et `SyncGuideMission` pour chaque prestation guide), chat, Contact, documents Drive.
3. **Parcours admin B2B** : création d'un dossier pour une agence (visible par elle), ajout de prestations par le répéteur, prestations internes masquées, total facturable et frais, changements de statut, annulation (`CancelGuideMission`), PDF récapitulatif, fax, étiquettes.
4. **Accès** : cloisonnement entre agences sur toutes les surfaces ; staff vs agence ; matrice des profils.
5. **Automatismes** : clôture par cron, tâches générées, décisions Calendar, relance des guides.
6. **Schéma du formulaire** : après extraction, `folderItemSchema('ordered')` produit les mêmes champs (noms, règles, visibilité) qu'avant ; test Livewire de création d'une prestation admin avec le même prix.

### 9.2 Scénarios du workflow interne (lot 3b)
1. Dossier interne + 5 prestations préparées → zéro `folder_items`, zéro job, zéro Calendar, zéro tâche, zéro email ; budget prévisionnel calculé.
2. Commande de 2 → exactement 2 `folder_items` en « En attente de validation » ; jobs guides en file **après commit** ; lot tracé ; 3 préparées intactes.
3. Modification des 3 restantes → aucune incidence sur les 2 commandées.
4. Nouvelle prestation préparée après commande → reste `draft`.
5. `ops_status` → « Réservations en cours » à la main → aucune conversion.
6. Suppression d'une préparée → aucun `CancelGuideMission`.
7. Annulation d'une commandée → `CancelGuideMission` si mission ; total recalculé ; budget mis à jour.
8. Rejeu de la même clé, et deux commandes concurrentes des mêmes lignes → une seule `folder_items` par préparée (index unique), réponse « déjà commandée ».
9. Deux commerciaux : formulaire périmé après une commande → enregistrement refusé, aucune perte.
10. Dossier B2B : section « En préparation » absente ; suite 9.1 verte.
11. Effets croisés : mission guide créée, mise à jour, annulée ; Calendar seulement après commit (rollback simulé → aucun appel) ; PDF sans les préparées ; prévisionnel et opérationnel sans double comptage ; tarif modifié entre préparation et commande → écart signalé, refus sans acceptation.
12. Test de parité du mapping (§3.4).

---

## 10. Risques
| Risque | Gravité | Atténuation |
|---|---|---|
| Extraction du schéma de prestation (formulaire partagé avec le B2B admin) | Moyenne | Déplacement sans modification ; test 9.1-6 ; recette visuelle sur un dossier B2B |
| Colonne ajoutée à `folder_items` sans être reportée en préparation | Moyenne | Test de parité qui bloque la suite |
| `syncGoogleCalendar` repoussé au commit | Faible | Effet nul hors transaction ; tests de référence Calendar |
| Passage du dossier interne à `confirmed` à la première commande | Faible | Les automatismes concernés ne lisent que `folder_items` ; test 2 |
| Prix « préparé » obsolète | Moyenne | Recalcul obligatoire au récapitulatif + acceptation explicite |
| Formulaire ouvert pendant une commande | Moyenne | `lock_version` du dossier + `lock_version` de la prestation préparée |

## 11. Ordre de réalisation
Lot 1 (tests de référence) → lot 2 (origine et accès) → lot 3a (socle des dossiers internes) → **lot 3b** (ce document, après ta validation).
