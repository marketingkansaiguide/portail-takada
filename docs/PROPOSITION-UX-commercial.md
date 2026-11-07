# Proposition : fiche dossier commerciale (2026-11-07)

Référence fonctionnelle : e-mail « NEW BOOKING » (transmission manuelle commercial → billetterie). Aucune donnée personnelle de cet e-mail n'est reprise dans le dépôt ni dans les recettes (identités fictives).

## 1. Diagnostic de l'existant (écran `/commercial/dossiers/{id}`)

- Un seul formulaire de ~2 300 lignes partagé avec l'admin : tout est au même niveau (informations, voyageurs, vol, documents, prestations, suivi).
- Champs de billetterie visibles du commercial : mode d'envoi de la billetterie, premier hébergement en texte libre, « nom réservation hôtel », chat, pré-facture.
- Voyageur : date de naissance et nationalité obligatoires, ce qui bloque la saisie quand elles sont inconnues (cas réel de l'e-mail : adultes sans date).
- Vol : un seul texte libre. Hébergement : un seul « premier hébergement », texte libre, sans lien au référentiel `hotels`.
- Politique tarifaire (`sale_type`, `pricing_client_group_id`) demandée au commercial et bloquante.
- Finance : « Achat 0 ¥ » affiché quand aucun coût n'est saisi (faux : inconnu).
- Aucune zone de consignes pour la billetterie (par prestation ou générale), aucun champ « référence devis ».
- Référentiel hôtels : `name, address, phone, google_maps_url` seulement. Ni ville, ni noms alternatifs, ni catégories de chambres.

## 2. Structure retenue

Onglets (une page, un seul bouton Enregistrer par onglet, état conservé dans l'URL) :

1. **Vue d'ensemble** : bandeau fixe (référence, nom, B2B/B2C, agence en info, réf. devis, dates, pax, statut, commercial) ; cartes d'attention (« 3 prestations sans horaire », « hôtel manquant le 05/01 », « consigne urgente ») ; vols en résumé ; hébergements en résumé ; compteurs préparé / commandé / en attente / confirmé ; consignes générales pour la billetterie (drapeau urgent) ; actions rapides (+ voyageur, + hôtel, + vol, + prestation, commander).
2. **Voyageurs** : tableau compact, panneau latéral d'édition, contact principal, tout facultatif sauf le nom.
3. **Vols** : segments (compagnie, n°, aéroports, départ et arrivée en heure locale), aller/retour/correspondances.
4. **Hébergements** : tableau trié par date ; ajout rapide, duplication ; hôtel choisi par recherche dans le référentiel, saisie libre en secours ; types de chambre dépendants de l'hôtel ; responsable de la réservation (Takada / agence / client) distinct du statut.
5. **Prestations** : préparées puis commandées (lot 3b inchangé), recherche catalogue, formulaire réduit, champ « Informations pour la billetterie ».
6. **Chronologie** : une ligne par jour (nuit, vols, transferts, visites, transports), préparées en pointillé / commandées en plein, alertes d'incohérence.
7. **Finances et suivi** : prévisionnel, commandé, prix de vente global, coûts, marge, avec états « non renseigné », « calcul incomplet », « aucune commande ».
8. **Historique** : section repliée en bas, hors onglets.

Masqué pour le commercial dans l'espace `/commercial` (non supprimé du backend) : pré-facture, chat, fournisseurs, mode d'envoi billetterie, missions guides, champs administratifs.

## 3. Données à ajouter (extensions minimales)

| Besoin | Modèle |
|---|---|
| Réf. devis, consignes générales, drapeau urgent | colonnes sur `folders` |
| Hébergements | table `folder_stays` (dossier, `hotel_id` nullable, nom et adresse conservés, dates, chambres, `room_type_id` nullable, libellé de chambre conservé, repas, statut, responsable, confirmation, notes, achat/vente facultatifs) |
| Référentiel | `hotels` + `city`, `alt_names` ; nouvelle table `hotel_room_types` (hôtel, libellé unique par hôtel) |
| Vols | table `folder_flight_segments` (heures locales + fuseau IANA) |
| Voyageurs | date de naissance et nationalité facultatives, téléphone, e-mail, `is_lead` |
| Note billetterie par prestation | `ticketing_notes` sur `folder_planned_items` et `folder_items` (copiée à la commande) |
| Lien rendez-vous | valeur explicite `meeting_point` sur la ligne, pré-remplie depuis séjour / vol, jamais recalculée |

Tarifs internes : un dossier interne n'exige plus de groupe client ; les prix internes viennent du catalogue standard, frais de dossier nuls. `sale_type` reste (B2B/B2C) pour les devis futurs. Le B2B agence n'est pas touché.

## 4. Ordre d'implémentation

1. Espace commercial simplifié (onglets, masquages, tarifs sans groupe, finance honnête).
2. Hébergements + référentiel hôtels / chambres.
3. Vols structurés.
4. Formulaire prestation allégé + notes billetterie.
5. Liens (rendez-vous depuis hôtel/vol, voyageurs).
6. Vue d'ensemble + chronologie.

Chaque bloc : migration réversible, tests (SQLite + MySQL), recette navigateur sur la base de recette, B2B vérifié par la suite de référence.
