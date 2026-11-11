# Étude de faisabilité : reprendre le suivi billetterie dans le portail

Version 1 · 2026-11-11 · Étude seule, aucun code ni écriture en prod.
Légende : **[C]** constaté (Excel ou code local) · **[H]** hypothèse à confirmer.

## 1. Ce que contient l'Excel

Fichier `[2026] Billetterie (3).xlsx` (le fichier cité « 2026 Billetterie 3.xlsx » n'existe pas sous ce nom ; c'est celui des Téléchargements).

| Onglet | Contenu | Reprenable ? |
|---|---|---|
| **RÉCAP RÉSAS** | Le vrai suivi : **une ligne par prestation et par client**, **6 522 lignes**, **799 clients**, dates du 07/01/2026 au 10/01/2027, achat total ≈ 107,7 M¥ [C] | Oui, c'est le sujet |
| TO-DO | ~65 tâches (client, date, tâche, statut, attribué à) | Existe déjà (`FolderTask`, tableau d'actions) |
| CALENDRIER | Rappels jour par jour | Existe déjà (Google Calendar) |
| Infos billetterie, Annuaire activités, Annuaire transporteurs, Liste prestataires, Tarifs trains, tarifs 2027 | Référentiels | Catalogue et fournisseurs : déjà repris (`suppliers:sync-excel`) ou à traiter à part |
| Dde IWA (sept à déc), Commande IC, Suivi IC | Commandes IWA et cartes IC | Déjà dans `IwaOrders` et `IcCards` |

Colonnes de RÉCAP RÉSAS [C] : nom client (agence + voyageurs + « (agence) » dans la même cellule), date de prestation, type de prestation (texte libre sur 2 à 3 lignes : nom, trajet, n° de réservation), départ/début, arrivée/fin, pax/J, PA unitaire TTC, PA total TTC, prise de contact, demande validée, date d'achat/réception facture, date de check-in, infos guide/chauffeur, lieu d'envoi, nom du premier hôtel, date d'envoi/remise, contact client. Les couleurs de cellules servent de statuts (légende : résa OK, en cours, annulée, infos manquantes, facture en attente).

## 2. Ce qui se reprend tel quel

| Excel | Portail |
|---|---|
| Client / agence | `Folder` (`agency_id`, `lead_traveler_name`, `folder_name`) |
| Date de prestation, début/fin, départ/arrivée | `FolderItem.service_date`, `end_date`, `meeting_time`, `end_time`, `route_from`, `route_to` |
| Pax/J | `pax_adults` / `pax_children` / `quantity` |
| PA unitaire / total TTC | `purchase_unit_price` / `purchase_total_price` |
| Date réception facture | `invoice_received_at` |
| Infos guide/chauffeur, remarques | `ticketing_notes`, champs `ticket_dispatch_*` du dossier |
| Premier hôtel | `hotel_booking_name` |
| Statut (couleur) | `ItemStatus` (déjà administrable) |
| Check-in | page `UpcomingCheckins` |
| Commandes IWA, 7 tickets, IC, trains | pages déjà en place |

## 3. Ce qui manque

1. **Dates de suivi par ligne** : « prise de contact », « demande validée », « date d'achat », « date d'envoi/remise ». Aucun champ équivalent sur `folder_items` (seul `label_exported_at` approche l'envoi). À créer : 4 colonnes date, ou un petit journal d'événements par ligne.
2. **Prestation libre** : le texte « Type de prestation » ne correspond à aucun `Product` de façon fiable (trajet, n° de résa, détails mélangés). Il faut un rattachement au catalogue ou une ligne libre (`FolderFreeLine`, `title` + `is_internal`).
3. **Séparation agence / voyageurs** : un seul champ texte à découper (1 espace, 2 lignes, parenthèse finale). 81 agences distinctes repérables par la parenthèse [C] ; à rapprocher des `agencies` existantes (risque d'homonymes).
4. **Écran de suivi par ligne** : une vue tableau type Excel (filtre par statut, par date, par « à faire »), qui regroupe toutes les lignes tous dossiers. Aujourd'hui les pages billetterie sont par type (trains, IWA, IC), pas un récap transversal.
5. **Statuts alignés sur la légende Excel** : vérifier que les `ItemStatus` couvrent « en attente d'infos », « facture en attente », etc.

## 4. Règles de rattachement (décidées par le coordinateur, relevé prod du 10/11)

La prod ne compte qu'environ **6 dossiers et 26 prestations** : les 799 clients ne sont pas dans le portail. L'import **crée donc des dossiers** (chiffres repris du relevé du coordinateur ; mon propre accès SSH a été refusé, non revérifié).

1. **Un dossier par client et par voyage** : lignes de même nom client, dates contiguës (règle à fixer, par exemple écart ≥ 30 jours entre deux voyages (décidé par Maxime) ; au-delà, nouveau voyage).
2. **Rattachement à un dossier existant uniquement** si le nom **et** les dates correspondent exactement ; sinon création.
3. **Règle d'origine (correction de Maxime)** : seules les lignes de l'agence **K.V. / KV / TAKADA TRAVEL** deviennent des dossiers **internes** (`origin = internal`). **Toutes les autres agences donnent des dossiers agence**, liés à leur agence ; si l'agence n'existe pas encore dans le portail, elle est **créée** (liste au §4 bis).
4. Les cas ambigus (homonymes, agence proche mais non identique) vont dans un rapport d'anomalies, pas d'import silencieux.

### 4 bis. Agences de l'Excel à rapprocher ou à créer [C, comptage Excel]

L'agence n'est qu'une parenthèse en fin de nom client : **66 écritures différentes, environ 50 agences réelles** après regroupement des variantes (casse, singulier/pluriel, fautes). Lignes de prestation entre parenthèses. Je n'ai pas pu lire les agences déjà présentes en prod : l'import en dry-run listera celles à créer.

- **Interne** : KV, K.V., K.V, K-V (≈ 1 060 lignes, ~75 clients) et TAKADA TRAVEL si présent.
- **Grandes agences** : Amplitudes / Amplitude (1 201), LMDV (1 121), Exotic / EXOTIC (1 154), Allibert Trekking (418), JTC (313), Japan Specialist (111).
- **Moyennes** : Emotika / Emotika Voyages (114), Du Reve O Voyage (72), Impact Voyage (44), 5 Continents (53), Ryoko Voyage(s) (56), Kaleidoscope / Kaleidoscpe (48), Cercle des Vacances (43), Cercle des Voyages (20, **à vérifier : même agence que Cercle des Vacances ?**), Human Trip, Zen Asie, Opale Voyage, Travel Experts, Saveurs du voyage, Eric and the trip, Rubis Voyage, BA Créatrice de voyages, Jancarthier, SATGURU TRAVEL, MAI GLOBE, CFA Voyages (≈ 35, 6 écritures), Atypika / Atypika Tours.
- **Petites (< 12 lignes)** : Kaliwa, My Luxury Travel, Joubert Voyages, BA BTS sa, Tour de Corée, Voyage à la carte, My Concierge, Collectif MYWAY, Contact Amérique Voyage, Time 2 Go, Le Palmier Voyageur, Easia Travel Japan, La Route du Japon, Instant de liberté, Lartisien, Cruiseline, Amerasia.
- **Pas de vraie agence** : « BtoC », « FAM Trip FIT », « Sortie Tokyo pôle FIT », « Aline CORBOZ » (nom de personne), et **229 lignes (29 clients) sans parenthèse**. **Choix retenu (modifiable)** : dossiers **internes** (ventes directes ou activités Takada).
- **« Cercle des Vacances » et « Cercle des Voyages »** : gardées comme **deux agences distinctes** (choix modifiable), signalées pour vérification dans le rapport du dry-run.

### 4 ter. Table de correspondance des graphies (à appliquer avant rapprochement)

Comparaison insensible à la casse, aux accents, au pluriel et à « & »/« and ». Graphies de l'Excel → agence unique :

| Agence unique | Graphies de l'Excel regroupées |
|---|---|
| **KV / Takada Travel (interne)** | KV, K.V., K.V, K-V, TAKADA TRAVEL, BtoC, FAM Trip FIT, Sortie Tokyo pôle FIT (confirmé par Maxime) |
| Amplitudes | Amplitudes, Amplitude |
| LMDV | LMDV |
| Exotic | Exotic, EXOTIC, La Route du Japon (confirmé par Maxime) |
| Allibert Trekking | Allibert Trekking, Allibert trekking |
| JTC | JTC |
| Japan Specialist | Japan Specialist, Japan specialist |
| Emotika | Emotika, Emotika Voyages |
| Ryoko Voyage | Ryoko Voyage, Ryoko Voyages |
| Kaleidoscope | Kaleidoscope, Kaleidoscpe (faute) |
| 5 Continents | 5 continents, 5 Continents |
| Cercle des Vacances | Cercle des Vacances, Cercle des vacances, Cercle des Voyages, Cercle des voyages (confirmé par Maxime) |
| Atypika | Atypika, Atypika Tours (confirmé par Maxime) |
| BA Créatrice de voyages / BA BTS sa | **deux agences distinctes** (confirmé par Maxime) |
| Travel Experts | Travel Experts, Travel expert |
| Saveurs du Voyage | Saveur du Voyage, Saveurs du voyage |
| Eric and the Trip | Eric and the trip, Eric&the trip, Eric and the Trip |
| CFA Voyages | CFA Voyages, CFA Voyage, CFA voyages, CFA voyage, CFA VOYAGE |
| Tour de Corée | Tour de Coree, Tour de Corée |
| Time 2 Go | Time 2 Go, Time 2 GO |
| Autres (graphie unique) | reprises telles quelles |

Doutes tranchés par Maxime le 2026-11-11 : Cercle des Vacances = Cercle des Voyages (une agence) ; Atypika = Atypika Tours (une agence) ; BA Créatrice de voyages et BA BTS sa restent deux agences. Cela remplace le choix « deux agences Cercle » du §4 bis.

Estimation : 799 clients donnent environ 800 à 1 100 dossiers selon le nombre de voyages multiples [H].

## 4 quater. Rapprochement des prestations vendues avec les produits du portail

Fait sur le **vrai catalogue de prod** (export en lecture seule du 2026-11-11 : **108 produits, 192 options, 5 catégories** : Activité 79, Guidage 22, Transports 4, Extras 2, Transferts aéroport 1). Une première version de cette section, fondée sur ma base locale (5 produits), était fausse et est remplacée.

Rapprochement par règles de familles puis par nom proche, sur les 6 522 lignes (indicatif, sans exigence de précision au niveau option) :

| Famille Excel | Lignes | Produit de prod |
|---|---|---|
| Trains, Shinkansen, bus (Nohi…), Fare Ticket, freepass | ≈ 3 680 | « Ticket de train / bus », « Hakone freepass » |
| Activités et visites (nom proche d'un produit/option) | ≈ 880 | produits Activité / Guidage |
| Pocket wifi | ≈ 470 | « Pocket wifi » |
| Carte IC | ≈ 340 | « Carte de transport IC » |
| Traduction de permis | ≈ 170 | « Traduction de permis » |
| Véhicule privé / transferts (reconnus) | ≈ 80 + à compléter | « Transfert privé aéroport » |
| **Non rapprochées** | **≈ 900 (14 %)**, 320 libellés distincts | à créer ou ligne libre |

Au total environ **86 % des lignes** trouvent un produit existant. Le calcul sous-estime un peu les véhicules privés (accents mal normalisés) ; les trajets de train, n° de résa et horaires restent dans `title`/notes puisqu'un seul produit « Ticket de train / bus » les porte.

**Principaux manques dans le catalogue** (à créer seulement après vérification qu'ils n'existent pas) : Location de voiture (88 lignes), USJ (≈ 95), Parc Ghibli (≈ 50), teamLab Planets / Borderless (≈ 56), Disney Sea / Land (≈ 32), Koya pass (16), Miyako Odori (16), Musée Ninja Kyoto (17), frais d'envoi / impression (≈ 25), Ferry Yaeyama, Villa Katsura, musées d'art (Chichu, Lee Ufan, Ando), Kurobe Torokko, Rapi:t Nankai.

Approche : (1) dictionnaire de règles mot-clé → produit/option, appliqué à l'import ; (2) création des ~10 à 15 produits manquants ci-dessus ; (3) le reste en **ligne libre** dans le rapport d'anomalies. Effort supplémentaire : **1,5 à 2 jours** (règles, produits manquants, revue), à ajouter au lot 3.
## 5. Options

**A. Import ponctuel**, à partir d'aujourd'hui seulement (lignes de date ≥ 2026-11-11), puis arrêt de l'Excel pour ces dossiers.
**B. Saisie continue dans le portail** pour les nouveaux dossiers ; l'Excel reste en lecture seule pour l'historique.
**C. Import + saisie** (A puis B). C'est la voie sans double saisie.

Contrainte technique [C] : créer un `FolderItem` déclenche des effets (mission guide, Google Calendar, total du dossier, historique). Un import doit les désactiver, sinon des missions et des événements partent pour des prestations déjà traitées. Le dossier interne et `folder_planned_items` (lots 2/3) ne conviennent pas à de l'historique : ils servent à préparer avant commande.

## 6. Effort par lot (ordre de grandeur, développeur + Claude)

| Lot | Contenu | Effort |
|---|---|---|
| 1. Cadrage | Règle de regroupement par voyage, colonnes réellement utilisées par l'équipe, statuts cibles | 0,5 j (avec l'équipe) |
| 2. Données | Migration : 4 dates de suivi + ligne libre ; statuts alignés | 0,5 j |
| 3. Import | Commande `billetterie:import-recap` : dry-run, découpage client, rapprochement agence/dossier, effets désactivés, rapport d'anomalies, idempotente (clé : fichier + n° de ligne) | 2 à 3 j |
| 4. Écran de suivi | Tableau « Récap résas » filtrable, édition rapide des dates et statuts, export | 2 à 3 j |
| 5. Recette | Revue des anomalies avec Erika/l'équipe, relevé comparatif Excel/portail | 1 j |
| **Total** | | **≈ 6 à 8 j** |

## 7. Risques

- **Volume de création** (≈ 800 à 1 100 dossiers, 6 522 prestations) : références, totaux, historique et notifications à neutraliser pendant l'import ; sauvegarde prod avant, dry-run obligatoire.
- **Faux regroupements** (deux voyages du même client fusionnés, ou un voyage coupé) : règle de dates à valider sur un échantillon.
- **Doublons** avec les 6 dossiers existants : rattachement seulement sur nom et dates exacts.
- **Texte libre** non structuré : environ une ligne sur deux restera en ligne libre, sans lien catalogue ni statistiques produit.
- **Effets de bord** (missions guides, Calendar) à neutraliser ; test sur une base copiée avant la prod.
- **Qualité de l'Excel** : 12 424 lignes de feuille pour 6 522 utiles, cellules à « × » ou à valeurs fantaisistes, formules en erreur (`#NAME?`, `#VALUE!` dans d'autres onglets).
- **Double saisie** pendant la transition si l'équipe garde l'Excel en parallèle.
- **Non vérifié** : contenu de la prod (nombre de dossiers, statuts existants) ; je n'ai lu que le code local et l'Excel.

## 8. Recommandation

Option **C**, en deux temps : (1) valider la règle de regroupement (§4) sur un échantillon ; (2) ajouter les dates de suivi et l'écran « Récap résas » (lots 2 et 4) **avant** l'import ; (3) importer en créant les dossiers, en commençant par les voyages à venir (le passé peut suivre si l'équipe le veut), puis geler l'Excel à une date de bascule. Les dossiers connus vont à leur agence, les autres en dossiers internes.

## 9. Décisions de Maxime (2026-11-11) pour le rapprochement

1. **Trains, bus, Fare Ticket, Hakone (≈ 3 680 lignes)** : produits existants « Ticket de train / bus » et « Hakone freepass » ; trajet, n° de train et horaire dans le détail de la ligne.
2. **Véhicule privé** (2/3/4 pax, avec ou sans guide, SK Limo) : **créer un produit « Véhicule privé », sur devis uniquement**, avec demande d'horaire, de point de pickup et de dépose.
3. **Location de voiture (88 lignes)** : **créer un produit dédié, sur devis uniquement**.
4. **USJ, Parc Ghibli, teamLab, Disney, Koya pass, Miyako Odori, musées d'art, etc.** : créer ces produits en catégorie Activité, avec **le fournisseur de la billetterie quand il est renseigné**.
5. **Prix de vente** : laissé vide à l'import (donnée absente de l'Excel ; disponible seulement dans les factures).
6. **Statuts : les couleurs de l'Excel sont fiables** et servent de source (pas les dates). Lecture technique vérifiée : la légende compte 8 couleurs (A2 vert B6D7A8, B2 jaune FFE599, C2 bleu A4C2F4, D2 rose F7A3F3, A3 orange F9CB9C, B3 gris B7B7B7, C3 violet B4A7D6) ; sur les 1 200 premières lignes : vert 1 141, gris 42, orange 9, jaune 2 ; rose (facture en attente) sur la colonne K.
   Correspondance proposée (statuts de prod : 1 attente d'ouverture/achat planifié, 2 Confirmé, 3 Annulé, 4 Pas de disponibilité, 5 En attente de validation, 6 En cours de traitement) :
   | Couleur | Sens Excel | Statut portail |
   |---|---|---|
   | Vert | Résa/achat/envoi OK, terminé | 2 Confirmé |
   | Orange | Demande en cours, attente de confirmation | 5 En attente de validation |
   | Bleu | Infos manquantes (date, vol, hôtel, guide, tarif) | 6 En cours de traitement |
   | Rose | Facture en attente de réception | 2 Confirmé, `invoice_received_at` vide |
   | Jaune | Notée dans le CALENDRIER | 1 En attente d'ouverture des réservations / Achat planifié (corrigé par Maxime) |
   | Violet | Notée dans le tableau Vision / IWA | 1 En attente d'ouverture des réservations / Achat planifié (corrigé par Maxime) |
   | Gris | Annulée | 3 Annulé |

## 10. Sens exact des quatre dates de suivi (précisé par Maxime, 2026-11-11)

| Colonne Excel | Sens | Champ cible |
|---|---|---|
| Prise de contact | Date d'envoi de la demande (fax ou mail) au fournisseur | `requested_at` (nouveau) |
| Demande validée | Date de confirmation par le fournisseur | `supplier_confirmed_at` (nouveau) |
| Date d'achat / de réception facture | Date de réception de la facture ; **date d'achat quand il n'y a pas de facture** (paiement CB ou espèces) | `invoice_received_at` (existe) + `purchased_at` (nouveau) |
| Date d'envoi / de remise | Date d'envoi de la billetterie à l'hôtel ou au guide | `delivered_at` (nouveau) |

Les champs `requested_at`, `supplier_confirmed_at`, `purchased_at` et `delivered_at` sont des noms proposés. À vérifier au codage : le lot 3b (`FolderOrderBatch`) enregistre peut-être déjà une date de commande au fournisseur, auquel cas « prise de contact » s'y rattache au lieu d'une nouvelle colonne.
