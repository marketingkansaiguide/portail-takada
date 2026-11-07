# Plan d'exécution — Portail interne (version en vigueur)

Mise à jour : 2026-11-07 · Branche : `feature/dossiers-internes` (locale, non poussée) · Aucune migration ni aucun déploiement en production sans l'autorisation explicite de Maxime.
Documents liés : `docs/LOT3-preparation-commande.md` (lot 3b, architecture B validée), `docs/DEPLOIEMENT.md`. Les versions précédentes sont dans `docs/historique/` (propositions en partie remplacées).

## 1. Règles en vigueur

1. **Une prestation interne n'est commandée que par une action explicite.** Le commercial prépare des prestations (`folder_planned_items`), puis sélectionne celles à commander. La commande crée les `folder_items` correspondants, dans une transaction. **Aucun statut de dossier ne déclenche de commande**, ni `ops_status` (avancement commercial), ni `status` (statut technique).
2. Le statut technique `status` d'un dossier interne vaut `draft` jusqu'à la **première commande effective**, puis `confirmed`. Il passe à `cancelled` ou `completed` quand l'avancement commercial passe à Annulé ou Terminé. Aucun automatisme ne lit les prestations préparées : elles sont dans une table distincte.
3. Un dossier interne n'est jamais visible par une agence, même associée (`client_agency_id`). Le seul point de contrôle est `App\Support\FolderAccess`.
4. La politique tarifaire d'un dossier interne est explicite (`sale_type`, `pricing_client_group_id`). Elle n'est jamais déduite de l'agence associée. Tant qu'elle n'est pas définie, elle est signalée, et elle bloquera la commande au lot 3b.
5. Le parcours B2B est inchangé. Il est protégé par les tests de référence du lot 1.

## 2. État réel (au commit indiqué)

| Lot | Contenu | Codé et commité | Testé | Commit |
|---|---|---|---|---|
| 0 | Procédure de sauvegarde et restauration ; restauration de la prod testée en local (MySQL 8.4) | Oui (documentation) | Restauration exécutée : comptages identiques, 108 migrations | `9e10102`, `303870d` |
| 1 | Tests de référence B2B : tarifs (6 200 scénarios sur le catalogue prod), rémunération guide (477 combinaisons), accès agence, parcours de validation agence, automatismes (guides, clôture, tâches), matrice des droits des 9 profils | Oui | Oui (voir §3) | `237cd51`, `303870d` |
| 2 | Origine des dossiers `b2b`/`internal` (obligatoire, sans défaut), `client_agency_id`, contraintes CHECK MySQL, `FolderAccess` branché sur policies, panel agence, fichiers, chat, Contact, fiche produit ; commande `folders:origin-report` | Oui | Oui ; migration jouée et annulée sur une copie de la prod (visibilité agence identique) | `525e129` |
| 3a | Dossiers internes : création distincte, type, avancement, responsable, contact, type de vente, politique tarifaire, notes, droits `folder.internal.*`, visibilité tous commerciaux, verrou optimiste, cron et tâches limités au B2B, PDF sans agence, liste et filtres | Oui | Oui | `7407bca` |
| 3b | Préparation puis commande (option B) et les garanties du §12 | **Non : planifié** | — | — |
| 4 et suivants | Données sensibles et 2FA, vols, hébergements, finances, tâches manuelles, tableau de bord, calendrier, devis | **Non : planifié** | — | — |

Non vérifié : aucune recette visuelle dans un navigateur (les écrans sont rendus par les tests Livewire et HTTP). Rien n'est déployé.

## 3. Tests

Commande : `php artisan test` (SQLite en mémoire). La même suite tourne aussi sur MySQL 8.4 (instance locale temporaire, variables `DB_*`), avec le même résultat attendu. Les services externes (Calendar, Drive, guides) sont neutralisés dans `phpunit.xml`.

Dossiers de tests :
- `tests/Feature/Reference/` : non-régression B2B (lot 1) ;
- `tests/Feature/Lot2/` : isolement des dossiers internes ;
- `tests/Feature/Lot3a/` : dossiers internes.

Les données de référence sont dans `tests/Fixtures/` : catalogue prod sans coordonnées fournisseurs, prix figés, matrice des droits.

## 4. Lots suivants (ordre)

1. **Recette visuelle des lots 2 et 3a**, puis **déploiement des lots 2 et 3a** si autorisé (procédure de `DEPLOIEMENT.md`).
2. **Lot 3b** : préparation et commande des prestations internes (`docs/LOT3-preparation-commande.md`, §1 à 12).
3. **2FA staff**, puis **lot 4** : données sensibles des voyageurs.
4. Lots 5 à 8 : vols, hébergements et finances, tâches manuelles et tableau de bord, calendrier.

Chaque lot passe par : une branche, des tests verts (SQLite et MySQL), un rejeu des migrations sur une copie de la prod, un compte rendu court, puis l'autorisation avant tout déploiement.

## 5. Constats hérités (non corrigés, à décider)

- `folders.agency_id` est en `ON DELETE CASCADE` : supprimer une agence supprime tous ses dossiers.
- `ActionBoardTaskGenerator::completeTaskByCode` attribue les tâches clôturées automatiquement à l'utilisateur n° 1 (erreur si ce compte n'existe plus).
- `agencies.email` et `agencies.client_group_id` sont obligatoires : la fiche agence créée depuis un dossier les demande.
