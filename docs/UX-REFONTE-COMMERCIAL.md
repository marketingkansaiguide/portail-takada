# Refonte UX de l'espace commercial : audit, proposition, tâches (2026-11-10)

Auteur : Fable 5.2 (rôle UX). Statut : **plan validé par Maxime le 2026-11-10, rien n'est encore implémenté**. T11 (hors catalogue) reste en attente de sa décision.
Remplace `docs/PROPOSITION-UX-commercial.md` pour la suite (ce dernier décrit la V1 déjà codée).

## 0. Ce qui a été réellement vérifié

Audit fait dans le navigateur le 2026-11-10, sur un serveur local isolé (base SQLite jetable, dossier fictif
« Famille EXEMPLE », compte `commercial-fit@recette.test`), en 1440 px et 1366 px de large, plus lecture du code
(`CommercialFolderForm`, `PlannedItemsSection`, `EditFolder::orderPlannedItemsAction`, `TripTimeline`, vues `filament/commercial/*`).

| Écran | Vérifié |
|---|---|
| Liste des dossiers, vue d'ensemble, hébergements, prestations (ligne dépliée), chronologie | navigateur + code |
| Création de dossier | navigateur (champs relevés) |
| Voyageurs, vols, fenêtre de commande | code seulement |
| Finances, tableau de bord | non vus (le contenu n'a pas pu être lu dans le navigateur) |
| Vue billetterie (admin) des notes | non vérifiée |
| Recette chronométrée « avant » | **non faite** : à mesurer en T0, avant tout développement |

## 1. Diagnostic

Gravité : **B** bloquant ou source d'erreur métier, **M** friction quotidienne forte, **m** confort.

| # | Constat | Gravité | Impact métier |
|---|---|---|---|
| 1 | Aucune case de sélection dans l'onglet Prestations. Le bouton « Commander les prestations sélectionnées » est dans l'en-tête de page et ouvre une fenêtre où l'on recoche tout depuis une liste de libellés. | B | La sélection se fait loin des lignes, sans voir horaires ni notes : risque de commander la mauvaise ligne ou d'en oublier. |
| 2 | « Nuit(s) sans hébergement : 17/12, 18/12… » alors que l'hôtel du 17/12 existe mais n'a pas de date de départ. Un séjour sans dates est compté comme une nuit sans hôtel. | B | Fausse alerte : le commercial apprend à ignorer la zone « À vérifier ». |
| 3 | Compteurs : « 3 commandées » et « 3 en attente de traitement » sont les mêmes prestations, affichées comme deux blocs de même niveau. | M | Lecture fausse de l'avancement. |
| 4 | Alertes « À vérifier » en texte seul, non cliquables, liste tronquée par « … ». | M | Il faut chercher soi-même l'élément fautif. |
| 5 | Une prestation dépliée mesure environ 900 px de haut pour 768 px d'écran. Prix unitaire et total modifiables au même niveau que la date. Le produit est centré, la date s'affiche « déc. 18, 2026 » alors que le reste est en jj/mm/aaaa. | M | Formulaire long, d'allure administrative, pour 8 à 20 lignes par dossier. |
| 6 | Les billets « Ticket de train / bus » sont indistinguables dans la liste et la chronologie : ni trajet ni heure d'arrivée (l'e-mail donne « 09:20 → 11:52 » et le trajet). | B | L'information clé de la billetterie n'existe que dans la note libre. |
| 7 | Hébergements : lignes repliées « dates · hôtel · ville · statut ». Ni nombre de chambres, ni catégories, ni repas visibles sans déplier. Une seule catégorie de chambre par séjour. | M | Demande explicite non couverte. Impossible de saisir 1 double + 1 twin. |
| 8 | Chronologie : seuls les jours ayant une ligne sont affichés. Les nuits ne sont pas représentées (seulement « arrivée » et « départ »). Tri par libellé, pas par heure : l'arrivée à l'hôtel passe avant le vol. Aucun lien vers la modification. | M | Les trous du programme sont invisibles, la vue ne sert pas à contrôler. |
| 9 | Titre de page « Modifier TKD-202611-FVTB ». La référence est répétée trois fois (fil d'Ariane, titre, bandeau). Le nom du voyage est en petit. | M | On ne sait pas d'un coup d'œil dans quel dossier on est. |
| 10 | Les 7 onglets débordent : 1071 px de contenu pour 1041 px disponibles à 1440 px, et 967 px à 1366 px. Barre de défilement horizontale permanente, « Finances et suivi » coupé. | M | Demande explicite (pas de défilement horizontal). |
| 11 | La vue d'ensemble mélange la synthèse en lecture et, dessous, un formulaire de 14 champs (identité du dossier) plus les consignes. | M | La synthèse n'est pas une synthèse, on défile à chaque ouverture. |
| 12 | Deux boutons d'enregistrement (« Enregistrer » en haut, « Sauvegarder les modifications » en bas) et un seul enregistrement global pour tous les onglets. La fenêtre de commande ignore les modifications non enregistrées. | B | On peut commander une version antérieure de la ligne que l'on vient de corriger. |
| 13 | Création de dossier : le champ « Politique tarifaire (groupe client) » est toujours présent. Ni dates ni nombre de voyageurs à la création, alors qu'ils sont obligatoires ensuite. | M | Contredit la règle validée, et impose deux écrans pour créer. |
| 14 | Liste des dossiers : action « Pré-facture » proposée au commercial, statuts bruts « draft » / « confirmed », référence en première colonne, « 0 JPY » pour un montant inconnu. | M | Fonction à masquer encore visible, coût inconnu affiché comme nul. |
| 15 | Rendez-vous d'une prestation : trois champs (liste de suggestions, texte, heure) avec deux textes d'aide longs. | m | Bruit visuel sur le champ le plus utilisé. |
| 16 | Vols : fuseau horaire à choisir dans la liste complète des fuseaux, alors qu'il se déduit du code aéroport. | m | Champ technique exposé. |
| 17 | Pocket wifi et carnet de voyage : absents du catalogue, donc impossibles à inscrire au dossier. | M | L'e-mail de référence n'est pas couvert à 100 %. |

Ce qui est bon et doit rester : la séparation préparation / commande, la valeur de rendez-vous figée sur la commande,
« réservé par » distinct du statut, l'historique replié, les états « non renseigné » en finance.

## 2. Décisions de conception

### D1. En-tête de fiche et navigation

```
Dossiers ›
Famille EXEMPLE — fêtes de fin d'année                    [Modifier le dossier]
17/12/2026 → 03/01/2027 · 17 nuits · 4 voyageurs (2 ad. 2 enf.) · Lucie
B2B · Agence Voyages Lumière · Devis DEV-0001 · TKD-202611-FVTB     [Réservations en cours ▾]
⚠ URGENT billetterie : bus à réserver pour le 23/12.                 (une ligne, clic = consignes)
──────────────────────────────────────────────────────────────────────────────
Synthèse   Voyageurs 4   Vols 2   Hôtels 6   Prestations 11   Planning   Finances
```

- Le titre est le nom du voyage. La référence TKD devient une information secondaire, copiable.
- Sept onglets à libellé court, sans icône : ils tiennent à 1280 px. Plus de défilement horizontal.
- L'en-tête reste collé en haut au défilement (nom, dates, consigne urgente, onglets).
- La consigne urgente apparaît dans l'en-tête sur tous les onglets, pas seulement en synthèse.
- L'identité du dossier (14 champs) sort de la synthèse : panneau latéral « Modifier le dossier ».
- Le statut du dossier se change depuis sa pastille. Rappel conservé : il ne commande rien.

### D2. Enregistrement

- Voyageurs, vols, hôtels et prestations s'éditent dans un **panneau latéral par élément**, avec son propre
  bouton « Enregistrer ». Chaque élément est enregistré seul. Plus de bouton global, plus de doublon en bas de page.
- Conséquence : la commande porte toujours sur des données enregistrées (corrige le constat 12).
- Le contrôle de modification concurrente existant (`lock_version`) est conservé à chaque enregistrement.

### D3. Synthèse (lecture seule)

```
À FAIRE POUR CE DOSSIER (4)
 ● Hôtel sans date de départ : Hôtel Exemple Asakusa (17/12)         Compléter ›
 ● Hôtel sans dates : Machiya Exemple Kyoto                          Compléter ›
 ● Transfert privé aéroport (17/12) sans lieu de rendez-vous         Ouvrir ›
 ● 8 prestations préparées, non commandées                           Voir ›

PRESTATIONS  11   ▓▓▓▓▓▓▓▓░░░   8 à commander · 3 commandées (3 en attente, 0 confirmée) · 0 annulée

VOLS                                   HÔTELS
Aller  XX454 CMB→NRT  17/12 08:10      17/12 → ?      Asakusa, Tokyo        1 ch.  Confirmé
Retour XX455 NRT→CMB  03/01 11:20      21/12 → 24/12  Kanazawa   3 n.       2 ch.  Confirmé
                                       29/12 → 01/01  Hakone     3 n.       Client
CONSIGNES BILLETTERIE                                              [Modifier]
```

- Une seule zone « À faire », une ligne par problème, chaque ligne ouvre l'élément concerné dans son panneau.
- Un seul indicateur de prestations : une barre et une phrase. « En attente » et « confirmée » sont des
  sous-états de « commandée », jamais des compteurs voisins.
- Règles d'alerte : `hôtel sans dates` (séjour dont il manque arrivée ou départ) est distinct de
  `nuit sans hébergement` (nuit du séjour couverte par aucun séjour **daté**). Les nuits d'un séjour incomplet
  ne sont pas comptées comme manquantes tant que ses dates ne sont pas connues : l'alerte porte sur le séjour.
  Un séjour réservé par le client couvre ses nuits et ne produit jamais d'action « à commander ».
- Les nuits manquantes sont regroupées en plages (« 25/12 → 28/12, 4 nuits »), jamais tronquées.

### D4. Hôtels

```
 Arrivée  Départ  Nuits  Hôtel · ville                  Chambres               Repas    Statut      Réservé par
 17/12    —       —      Hôtel Exemple Asakusa · Tokyo  1 Familiale 4 pers.    Petit-déj Confirmé   Takada      ⋯
 21/12    24/12   3      Hôtel Exemple Kanazawa         1 Double + 1 Twin      Petit-déj Confirmé   Takada      ⋯
 29/12    01/01   3      Location libre · Hakone        —                      —        —           Client      ⋯
 ── 25/12 → 28/12 : 4 nuits sans hébergement ─────────────────────────  [Ajouter un hôtel sur ces dates]
 [+ Ajouter un hôtel]
```

- Tableau trié par date, toutes les informations demandées visibles sans déplier. Les trous apparaissent **dans**
  le tableau, à leur place chronologique, avec un ajout prérempli sur ces dates.
- Ajout : un seul champ de recherche (nom, variante, ville). Choisir un hôtel remplit nom, ville et adresse.
  Si l'hôtel n'existe pas : « Utiliser “xxx” comme saisie libre », sans changer de champ.
- Dates : arrivée, puis **nombre de nuits ou départ** (l'un calcule l'autre). L'arrivée proposée par défaut est le
  départ du séjour précédent.
- Chambres : plusieurs lignes « quantité × catégorie ». Les catégories viennent de l'hôtel choisi, avec saisie libre
  possible. Changer d'hôtel vide les catégories issues de l'ancien hôtel et le dit (« 2 catégories retirées »).
  Les libellés saisis à la main sont conservés.
- Menu ⋯ : modifier, dupliquer (dates décalées à la suite), « reprendre les chambres de… », supprimer.
- Détails secondaires dans le panneau : référence de confirmation, voyageurs concernés, adresse, notes, prix.
- Donnée nécessaire : plusieurs catégories par séjour. Proposition minimale : colonne JSON `room_lines` sur
  `folder_stays` (`[{room_type_id, label, quantity}]`), les colonnes actuelles restant alimentées par la première ligne.

### D5. Prestations (travail principal)

```
 [ Rechercher une prestation : nom, ville, catégorie…        ]  [Date]  [Pax 4]  [Ajouter]   + Hors catalogue

 À COMMANDER (8)                                              ☐ Tout sélectionner
 ☐ 18/12 09:00  Visite guidée journée – Tokyo   4 pax  RDV Hôtel Asakusa        29 000 ¥   ⋯
 ☐ 21/12 09:20  Train Kanazawa → Takayama (arr. 11:52)  4 pax   📝 bus Nohi…     4 600 ¥   ⋯
 ☐ 26/12  ⚠ heure manquante  Visite guidée journée – Kyoto  4 pax               38 000 ¥   ⋯
 ...
 COMMANDÉES (3)
   17/12 08:30  Accueil aéroport long + guidage  RDV NRT, vol XX454   En attente de validation
 ANNULÉES (0)                                                                       (replié)

 ▌3 sélectionnées · 71 600 ¥                         [Tout désélectionner]  [Commander les 3 prestations…]
```

- **Une seule liste**, groupée par état, triée par date puis heure. Les prestations commandées n'ont pas de case.
- **La sélection se fait sur les lignes.** Une barre fixe en bas d'onglet apparaît dès qu'une case est cochée.
  Le bouton de l'en-tête de page disparaît.
- « Commander… » ouvre le récapitulatif existant (prix recalculés, acceptation des nouveaux tarifs, tout ou rien,
  clé d'idempotence, `lock_version`) **déjà limité aux lignes cochées**. Le récapitulatif ajoute rendez-vous, heure
  et note de chaque ligne. Rien ne change dans `PlannedItemOrderService`.
- Une ligne incomplète (date manquante) est cochable mais le récapitulatif la bloque avec le motif, comme aujourd'hui.
- **Ajout rapide** en trois champs (prestation, date, pax), puis ouverture directe du panneau pour le reste.
  Date par défaut : celle de la dernière ligne ajoutée. Pax par défaut : celui du dossier.
- **Recherche** côté serveur à partir de 2 caractères, 15 résultats, sur nom, ville, catégorie, variantes.
  Résultat sur deux lignes : nom, puis catégorie et ville. Champ vide : les 8 prestations les plus préparées par
  l'utilisateur sur 90 jours (requête simple sur `folder_planned_items`). Le catalogue n'est jamais chargé en entier.
- **Panneau d'une prestation** : dans l'ordre, prestation, date, pax, options du produit, bloc contextuel,
  note billetterie. Le prix est affiché en une ligne en lecture (« 29 000 ¥, 7 250 ¥ × 4 ») et se déplie avec
  « Ajuster le prix ». Aucun champ fournisseur.
- **Bloc contextuel selon un profil de saisie**, déduit de la catégorie du produit (surcharge possible par produit) :

| Profil | Champs affichés |
|---|---|
| Visite, activité | heure, lieu de rendez-vous |
| Accueil aéroport | vol du dossier (liste), heure d'arrivée reprise du vol, lieu de prise en charge |
| Transfert | heure, départ, arrivée (suggestions : hôtels et vols du dossier) |
| Train, bus | gare de départ, gare d'arrivée, heure de départ, heure d'arrivée |
| Location, pass | date de début, date de fin, lieu de remise |
| Autre | heure et lieu facultatifs |

  Les champs propres au produit déjà définis au catalogue (`custom_fields`, options) restent affichés tels quels.
  Données nécessaires : `route_from`, `route_to`, `end_time`, `end_date` sur `folder_planned_items` et
  `folder_items`, copiés à la commande comme `meeting_point`. Le libellé de liste devient « Train Kanazawa → Takayama ».
- **Rendez-vous** : un seul champ texte avec suggestions (hôtel de la nuit précédente en premier, puis vols du jour),
  plus l'heure. La valeur reste figée sur la commande.
- **Hors catalogue** (pocket wifi, carnet de voyage, attention client) : ligne « À fournir, sans commande
  fournisseur », avec intitulé, date, quantité, note. Elle apparaît dans la liste et le planning, se transmet à la
  billetterie comme une tâche, et ne crée ni commande fournisseur, ni mission guide, ni événement d'agenda.
  **Décision à prendre par Maxime** : ligne libre (recommandé, aucune fausse commande) ou création de deux produits au catalogue.

### D6. Consignes pour la billetterie : trois niveaux, trois emplacements

| Niveau | Saisie | Affichage commercial | Affichage billetterie |
|---|---|---|---|
| Urgent (dossier) | case + texte court, panneau « Consignes » | bandeau rouge dans l'en-tête, tous onglets | bandeau rouge en tête du dossier admin |
| Général (dossier) | texte, même panneau | bloc en bas de synthèse | bloc replié « Consignes du commercial » |
| Par prestation | champ du panneau prestation | 📝 et première ligne dans la liste, texte entier au survol et dans le panneau | colonne dédiée sur la prestation commandée, et dans le récapitulatif opérationnel |

- La consigne urgente et la consigne générale deviennent deux champs distincts (aujourd'hui : un texte et un drapeau).
- Aucune de ces notes ne part vers un fournisseur, un guide ou une agence : à couvrir par un test sur les gabarits d'e-mail.
- Toute modification après commande est tracée dans l'historique (auteur, date, ancienne et nouvelle valeur) et
  marquée « modifiée le … » côté billetterie.

### D7. Planning (chronologie)

```
 jeu. 17/12  J1   ✈ 08:10  Arrivée NRT, XX454                                          Modifier
                  ● 08:30  Accueil aéroport long + guidage          En attente
                  ● 08:30  Transfert privé aéroport                 En attente
                  🏨 Nuit : Hôtel Exemple Asakusa, Tokyo
 ven. 18/12  J2   ○ 09:00  Visite guidée journée – Tokyo            Préparée
                  🏨 Nuit : Hôtel Exemple Asakusa, Tokyo
 sam. 19/12  J3   Journée libre
                  🏨 Nuit : Hôtel Exemple Asakusa, Tokyo
 ven. 25/12  J9   Journée libre
                  ⚠ Nuit sans hébergement                                 Ajouter un hôtel
```

- Liste verticale, **un bloc par jour du séjour, tous les jours**, y compris sans prestation. Pas de grille horaire
  ni de glisser-déposer : un dossier de 18 jours doit se lire en défilant, et s'imprimer.
- Chaque jour se termine par sa nuit (hôtel, ou alerte). Le changement de ville est visible par le changement d'hôtel.
- Tri par heure réelle. Les éléments sans heure viennent après, marqués « heure à préciser » si le profil en attend une.
- Type par pictogramme (vol, transport, visite, hôtel). Statut par forme et libellé : ○ préparée, ● commandée,
  ✔ confirmée, barré pour annulée. La couleur n'est jamais le seul signal.
- Chaque ligne ouvre le panneau de l'élément. Les incohérences s'affichent sur le jour concerné : prestation avant
  l'arrivée du vol ou après le départ, prestation hors des dates du séjour, deux éléments à la même heure.

### D8. Création, liste, voyageurs, vols

- **Création** en un écran : nom, dates, adultes, enfants, type de vente (facultatif), agence (information),
  commercial. Retrait de « Politique tarifaire ». Arrivée directe sur la fiche.
- **Liste** : nom du voyage, dates, voyageurs, commercial, avancement, « à commander / commandées », montant
  (« — » si inconnu). Référence en fin de ligne. Retrait de « Pré-facture », libellés de statut en français.
- **Voyageurs** : tableau (nom, prénom, naissance ou âge, principal, pictogrammes besoins), ajout en ligne
  nom + prénom, détail dans le panneau. Bouton « Mettre à jour le nombre de voyageurs du dossier » si l'écart existe.
- **Vols** : sens, n° de vol, aéroports, date et heure. Le fuseau est déduit du code aéroport et masqué.
  Il n'est demandé que si le code est inconnu.
- **Accessibilité** : cibles de 36 px minimum, focus visible, ordre de tabulation dans les panneaux, Entrée valide
  l'ajout rapide, Échap ferme le panneau, messages d'erreur sous le champ avec le moyen de corriger.

## 3. Priorités

1. **P1** : notes billetterie de bout en bout (D6), sélection et commande dans l'onglet Prestations (D5), correction des alertes d'hébergement (D3).
2. **P2** : formulaire prestation contextuel et recherche catalogue (D5), tableau des hôtels et chambres multiples (D4).
3. **P3** : en-tête, onglets, enregistrement par élément (D1, D2), synthèse (D3), planning (D7).
4. **P4** : création, liste, voyageurs, vols (D8), hors catalogue (D5, après décision).

## 4. Tâches pour le développement (Sonnet)

Règles communes : branche locale `feature/dossiers-internes`, aucun déploiement, migrations réversibles et non
destructives, B2B et admin inchangés (suite de référence verte), tests SQLite et MySQL 8.4, pas de refactorisation
hors périmètre. Les seeders de recette coupent déjà Google Agenda et Planning Guides : ne pas contourner.
Toute divergence avec une décision D1 à D8 remonte au coordinateur avant d'être codée.

**T0. Mesure de référence.** Chronométrer sur la version actuelle la saisie du dossier fictif complet (création,
4 voyageurs, 2 vols, 6 hôtels, 11 prestations, consignes, commande de 3 lignes) : durée, nombre de clics, nombre de
ressaisies. Livrable : tableau dans ce document. Aucun code.

**T1. Notes billetterie de bout en bout (D6).**
Réutiliser : `ticketing_notes`, `meeting_point`, `meeting_time`, `PlannedItemMapper`, `FolderHistory`.
Ajouter `ticketing_urgent_note` sur `folders` (le drapeau actuel est conservé, repris par migration).
Acceptation : note saisie sur une ligne préparée, retrouvée à l'identique sur la prestation commandée dans l'admin
et dans le récapitulatif opérationnel ; modification après commande tracée ; aucun gabarit d'e-mail fournisseur,
guide ou agence ne contient ces champs (test dédié) ; bandeau urgent visible côté admin.
Risque : gabarits d'e-mail existants qui parcourent tous les champs de la prestation.

**T2. Sélection et commande dans la liste (D5).**
Réutiliser : `orderPlannedItemsAction`, `PlannedItemOrderService::summary` et `order`, sans les modifier.
Acceptation : cases sur les lignes préparées ; barre de sélection avec total ; le récapitulatif s'ouvre avec ces
lignes seulement ; commande partielle ; double clic sans double commande ; modification concurrente refusée ;
plus de bouton dans l'en-tête ; un changement de statut du dossier ne commande rien.
Risque : état Livewire de la sélection après enregistrement d'une ligne.

**T3. Alertes d'hébergement justes et cliquables (D3).**
Réutiliser : `TripTimeline::alerts`, à faire renvoyer des alertes structurées (`type`, `cible`, `libellé`, `plage`).
Acceptation : séjour sans départ → « hôtel sans date de départ », aucune nuit manquante déduite ; trou réel →
plage regroupée ; séjour client → nuits couvertes, aucune action de commande ; chaque alerte ouvre le bon élément.
Risque : faible. Les tests actuels de `TripTimeline` sont à adapter.

**T4. Panneau prestation contextuel (D5).**
Réutiliser : `FolderResource::folderItemSchema('commercial')`, `MeetingSuggestions`, options et `custom_fields` du catalogue.
Ajouter le profil de saisie (catégorie, surcharge produit) et les colonnes `route_from`, `route_to`, `end_time`, `end_date`.
Acceptation : chaque profil n'affiche que ses champs ; changer de produit change le bloc sans perdre date, pax et
note ; prix replié ; libellé « Train A → B » dans la liste, le planning et la commande ; hauteur du panneau
inférieure à 700 px pour une visite guidée.
Risque : `folderItemSchema` est partagé avec l'admin et le B2B. Tout ajout doit être conditionné au mode `commercial`.

**T5. Recherche catalogue (D5).**
Acceptation : requête serveur, 15 résultats maximum, nom, ville, catégorie, variantes ; champ vide → prestations
fréquentes de l'utilisateur ; moins de 300 ms sur le catalogue réel ; le catalogue n'est jamais préchargé.
Risque : les droits internes donnent accès à tout le catalogue, sans filtre de groupe client. Ne pas réutiliser la requête B2B.

**T6. Hôtels en tableau, chambres multiples (D4).**
Réutiliser : `FolderStay`, `Hotel`, `HotelRoomType`, `searchHotels`. Ajouter `room_lines`.
Acceptation : toutes les colonnes visibles sans déplier ; trous affichés dans le tableau ; changement d'hôtel →
catégories de l'ancien hôtel retirées avec message ; saisie libre conservée ; duplication et reprise des chambres ;
nuits calculées dans les deux sens.
Risque : reprise des séjours existants (une ligne `room_lines` à créer depuis `room_type_id`, `room_label`, `rooms`).

**T7. En-tête, onglets, enregistrement par élément (D1, D2).**
Réutiliser : vue `filament/commercial/header`, actions de panneau latéral Filament, `lock_version`.
Acceptation : aucun défilement horizontal à 1280 px ; titre = nom du voyage ; un seul enregistrement par élément ;
plus de bouton global ; consigne urgente visible sur tous les onglets.
Risque : **le plus élevé du chantier**. Les répéteurs liés au formulaire global sont remplacés par des actions
par élément : tester la non-régression de l'historique et du verrou.

**T8. Synthèse (D3).** Dépend de T3 et T7. Acceptation : zone « À faire » unique, indicateur de prestations unique,
aucun champ de saisie dans l'onglet.

**T9. Planning (D7).** Dépend de T3 et T4. Réutiliser `TripTimeline::build`.
Acceptation : tous les jours du séjour, nuit en fin de jour, tri par heure, clic vers le panneau, incohérences sur le jour.

**T10. Création, liste, voyageurs, vols (D8).**
Acceptation : création en un écran sans politique tarifaire ; liste sans « Pré-facture » et sans statut brut ;
fuseau déduit de l'aéroport.

**T11. Hors catalogue.** En attente de la décision de Maxime (D5).

Ordre : T0, puis T1, T2, T3 (indépendantes), puis T4, T5, T6, puis T7, T8, T9, puis T10.
Après chaque lot : revue du rendu réel dans le navigateur par le rôle UX avant de passer au suivant.

## 5. Couverture de l'e-mail de référence

| Information de l'e-mail | État actuel | Après refonte |
|---|---|---|
| Nom, B2B/B2C, agence, réf. devis, dates | couvert | couvert |
| Montant de vente global | non vérifié (onglet Finances non vu) | à vérifier en T0 |
| Voyageurs, dates de naissance facultatives | couvert | couvert |
| Vols aller et retour | couvert | couvert |
| Hôtels, statut, réservé par le client | couvert, une seule catégorie de chambre | couvert (T6) |
| Prestations datées, quantités | couvert | couvert |
| Horaires et trajets de train | note libre seulement | champs dédiés (T4) |
| Lieux de rendez-vous | couvert | couvert, saisie simplifiée |
| Consigne urgente billetterie | couvert côté commercial, côté billetterie non vérifié | couvert (T1) |
| Cartes IC | couvert (produit au catalogue de recette) | couvert |
| Pocket wifi, carnet de voyage | **non couvert** | T11, selon décision |

## 6. T0 : mesure de référence (réalisée le 2026-11-10 par Sonnet)

Méthode : deux serveurs de recette isolés (SQLite jetable, Agenda et Planning Guides vides), l'un sur le commit
`e89b15c` (« avant », worktree séparé), l'autre sur le code courant. Dans chaque navigateur, ouverture du formulaire
d'ajout de chaque type d'élément et décompte des champs visibles (script de mesure, pas de saisie humaine) ; durées
**non chronométrées** : une saisie humaine ne se simule pas honnêtement. Les totaux du dossier de démonstration
(4 voyageurs, 2 vols, 6 hôtels, 11 prestations) sont des multiplications, pas des mesures.

| Élément (un exemplaire) | Champs visibles AVANT (obligatoires) | Champs visibles après lots 1 et 2 (obligatoires) |
|---|---|---|
| Création du dossier | 11 (4) ; ni dates ni voyageurs : 2 champs obligatoires à saisir ensuite sur la fiche | non refait (T10) |
| Voyageur | 10 (2) | 10 (2) |
| Vol | 10 (0), deux fuseaux à choisir dans la liste complète | 10 (0) (T10) |
| Hébergement | 18 (3), une seule catégorie de chambre | 18 (3), plusieurs catégories, nuits ou départ liés |
| Prestation (visite) | 10 (3), dont 2 prix en lecture seule et le bloc d'options | 6 (3) |
| Prestation (train) | visite + bloc Trajets, champs fournisseur et prix d'achat visibles | bloc Trajets seul, fournisseur et achat masqués |

Dossier de démonstration complet, par multiplication (champs à parcourir, hors champs facultatifs laissés vides) :
- AVANT : 11 + 4×10 + 2×10 + 6×18 + 11×10 = **289 champs parcourus**, dont 4 + 8 + 0 + 18 + 33 = 63 obligatoires ;
  un seul enregistrement global pour tous les onglets (bouton en haut et en bas) ; 7 onglets avec défilement
  horizontal ; commande depuis un bouton d'en-tête qui rouvre la liste des lignes.
- Après lots 1 et 2 : 11 + 40 + 20 + 108 + 11×6 = **245 champs parcourus** (-15 %), sans compter le gain des champs
  préremplis (pax du dossier, date de la dernière ligne, arrivée de l'hôtel suivant, premier trajet d'un train).
- Ressaisies identifiées avant : date et pax de chaque ligne, ville de l'hôtel, fuseaux des vols, trajet d'un train
  (date et pax déjà saisis sur la ligne) ; supprimées ou préremplies depuis.

À refaire sur le code final après T7 à T10 (même script).
