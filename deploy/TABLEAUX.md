# Inventaire des tableaux et listes

Objectif : aucun défilement horizontal (page ni tableau) de 360 à 1536 px, tri sur
tous les tableaux, actions atteignables à la souris, au clavier et au tactile,
modification dans une fenêtre modale (plus d'édition en ligne).

## Système partagé (phase 1)

| Élément | Rôle |
|---|---|
| `resources/js/Components/DataTable.vue` | Tableau piloté par une définition de colonnes (`cle`, `libelle`, `triable`, `cleTri`, `valeurTri`, `valeur`, `alignement`, `priorite`, `principale`, `largeur`, `largeurMin`, `tronquer`, `libelleMasque`, `carte`) et des slots `cellule-<cle>`, `entete-<cle>`, `actions`, `vide`. À partir de `lg` : `table-layout: fixed`, largeurs calculées en pixels depuis la largeur mesurée du conteneur, colonnes secondaires masquées par priorité et regroupées en sous-texte dans la cellule principale. Sous `lg` (ou conteneur trop étroit) : une carte par ligne + sélecteur « Trier par » et sens. Compteur `aria-live`, états vide / aucun résultat / chargement, `caption`, `scope`, `aria-sort`. |
| `resources/js/Components/ActionsMenu.vue` | Menu « ⋯ » accessible (bouton libellé, `aria-haspopup`/`aria-expanded`, flèches, Début/Fin, Échap avec retour du focus, Tab, clic extérieur), téléporté dans `<body>` en position fixe (jamais rogné, jamais de défilement). |
| `resources/js/Components/SortableHeader.vue` | En-tête triable (bouton 44 px, `aria-sort` sur la colonne triée, consigne lue par les lecteurs d'écran). Rétro-compatible avec les pages qui l'utilisaient déjà. |
| `resources/js/Components/Pagination.vue` | Pagination Laravel (liens conservant recherche, filtres et tri, cibles 44 px). |
| `resources/js/Components/Modal.vue` | Étendu : focus initial dans la fenêtre, retour du focus à la fermeture, Échap ne ferme que la fenêtre du dessus (pile), `aria-labelledby`. Le `<dialog>` natif rend le reste de la page inerte (focus piégé). |
| `resources/js/composables/useTableSort.js` | Tri client stable (français, sans accents ni casse, nombres et booléens), accesseurs par colonne, `setSort`. |
| `resources/js/composables/useMediaQuery.js` | Point de rupture `lg` réactif. |
| `app/Support/TriServeur.php` | Tri serveur : paramètres `tri` / `sens` validés par liste blanche (clé publique → colonne SQL ou closure). Convention : clé inconnue ou sens invalide ignorés → ordre par défaut (pas de 422, comme les filtres). Départage final sur la clé primaire (ordre stable entre les pages). |

## Inventaire

État : **toutes les pages sont migrées** (phases 1 et 2 terminées). Aucun
`overflow-x-auto` ne subsiste dans `resources/js` ; aucune édition en ligne.

Légende tri : **client** = liste complète en mémoire (`useTableSort`) ;
**serveur** = liste paginée (`TriServeur`, paramètres `tri` / `sens`).

| Page | Composant | Colonnes triables | Tri | Pagination | Actions / modales | État |
|---|---|---|---|---|---|---|
| Paramètres → Utilisateurs | `Settings/Users/Index.vue` | Nom, Prénom, E-mail, Rôle, Statut, Accès, Shifts gérés, Lié à un servant(e) | serveur (`UserController::triUtilisateurs`) | 30/page | Modifier (modale, shifts gérés inclus), Nouveau compte (modale) ; ⋯ Délier, Supprimer | Migrée |
| Servant(e)s / Recommandés | `Servants/Index.vue` | Nom, Prénom, Statut, Pieu (ordre des colonnes déplaçable) | client | Non | Voir ; Ajouter | Migrée |
| Tableau de bord (Conseil / Autres) | `Dashboard/Admin.vue` | Relèves, permutations, appels : Shift, Nom, Raison, Date, Résultat… | client | Non (N récents) | **Statuer** sur une relève (modale) ; Statuer → / Suivre → / Détails → (liens) | Migrée |
| Tableau de bord (Coordonnateur) | `Dashboard/ChefEquipe.vue` | Permutations : Nom, Shift de/à, Date, Résultat / État | client | Non | Liens | Migrée |
| Tableau de bord (Servant) | `Dashboard/Servant.vue` | Poste, Shift, Jour, Horaire, Depuis le | client | Non | — | Migrée |
| Shifts | `Shifts/Index.vue` | Shift, Jour, Horaire, Genre (Postes : non triable côté serveur) | serveur | 20/page | Voir | Migrée |
| Fiche d'un Shift | `Shifts/Show.vue` | Rôle, Titulaire, Appel, Protection de l'enfance, Badge, Photo | client | Non | **Affecter** (modale), **Modifier les étapes** (`EtapesAffectationModal`), Ajouter un servant(e) (modale) ; ⋯ Retirer du rôle, Supprimer le rôle | Migrée |
| Mon Shift | `Shifts/MonShift.vue` | Coordination : Nom, Rôle, Depuis ; postes : Rôle, Titulaire, Appel, étapes | client | Non | Modifier les étapes (modale, coordonnateur du shift) | Migrée |
| Changement | `ShiftTransfers/Index.vue` | Servant(e), Type, Shift, Date de demande, Statut | serveur | 30/page | **Traiter / Détails** (modale : suivi, validations, résultat), Nouvelle demande (modale) ; ⋯ Supprimer la demande | Migrée |
| Servant(e)s relevé(e)s | `ShiftTransfers/Releves.vue` | Servant(e), Shift, Date de résultat | serveur | 30/page | Réintégrer (modale `ReintegrationDialog`) | Migrée |
| Recrutement | `Recruitment/Index.vue` | Shift, À recruter, Échéance, Notes | client | Non | Modifier → **Besoin de recrutement** (modale) | Migrée |
| Rapports | `Reports/Index.vue` | Remplissage : Shift, Jour, Postes vacants, Taux | client | Non | Export CSV / PDF | Migrée |
| Modèles de Shift | `ShiftTemplates/Index.vue` | Nom, Postes | client | Non | Voir | Migrée |
| Fiche d'un modèle | `ShiftTemplates/Show.vue` | Liste ordonnée des postes (ordre métier, pas de tri par colonne) | — | Non | Modifier le nom (modale), déplacer (glisser / flèches) ; ⋯ Retirer du modèle | Migrée |
| Fiche d'un servant(e) | `Servants/Show.vue` | Historique : Poste, Shift, Début, Fin ; relèves : Shift, Relevé(e) le, Motif, Par, Réintégration | client | Non | — | Migrée |
| Paramètres → Rôles | `Settings/Roles/Index.vue` | Rôle, Gère des shifts, Type, Description, Clé technique | client | Non | Nouveau rôle / Modifier (modales) ; ⋯ Supprimer le rôle | Migrée |
| Paramètres → Pieux | `Settings/Pieux/Index.vue` | Nom, Type, Rattaché(e) à, Unités rattachées | client | Non | Modifier (modale) ; Supprimer | Migrée |
| Paramètres → Horaires | `Settings/Horaires/Index.vue` | Nom, Début, Fin | client | Non | Modifier (modale) ; Supprimer | Migrée |
| Paramètres → Étapes du parcours | `Settings/WorkflowSteps/Index.vue` | Ordre (défaut), Étape, Clé technique | client | Non | Modifier (modale), réordonner ; Supprimer | Migrée |
| Paramètres → Journal d'activité | `Settings/ActivityLog/Index.vue` | Date (défaut ↓), Action, Sur quoi, Par qui | serveur (`ActivityLogController::triActivites`) | 30/page | Détails (modale avant/après) | Migrée |
| Propriétaire → Licences | `Owner/Licenses/Index.vue` | Organisation, Comptes, Statut, Expiration | client | Non | Modifier la licence (modale), Créer une organisation (modale) | Migrée |
| Modifier un Shift, Mon servant(e), Paramètres → Licence | `Shifts/Edit.vue`, `Servants/MonServant.vue`, `Settings/Licence/Index.vue` | Formulaires / fiches, pas de tableau | — | — | — | Hors périmètre |

Composants partagés complétés lors des finitions : `SearchInput.vue` (hauteur
44 px, bouton d'effacement 40 px) et `SearchableSelect.vue` (motif ARIA
combobox / listbox : `aria-expanded`, `aria-controls`, `aria-activedescendant`,
flèches, Début/Fin, Entrée, Échap qui ne ferme que la liste, pas la modale).

## Règle pour les futurs tableaux

1. **Toujours `DataTable.vue`** : pas de `<table>` écrit à la main dans une page,
   pas de liste de cartes ad hoc pour des données tabulaires.
2. **Tri obligatoire** sur toutes les colonnes porteuses de données
   (`triable: true`, `valeurTri` si l'affichage diffère de la valeur). Liste
   complète → tri client ; liste paginée → tri serveur via `TriServeur`
   (liste blanche, départage sur la clé primaire, tri conservé dans les liens
   de pagination, de recherche et de filtre).
3. **Pas d'édition en ligne** : une action visible (Voir, Modifier, Traiter…),
   le reste dans `ActionsMenu` (⋯) ; toute saisie se fait dans `Modal.vue`.
4. **Jamais de `overflow-x-auto`** (ni de largeur minimale qui force un
   défilement) : déclarer `priorite`, `largeurMin`, `tronquer` sur les colonnes
   et laisser DataTable regrouper / passer en cartes.
5. Cibles tactiles de 44 px minimum, libellés accessibles explicites
   (`aria-label` incluant le nom de la ligne), textes longs coupés avec
   `title` ou `[overflow-wrap:anywhere]`.
6. Vérifier avec la mesure Playwright ci-dessous avant de livrer.

## Vérification

Mesure Playwright (Chrome) sur base jetable avec données longues (noms composés,
e-mails de 80 caractères, servants liés, coordonnateurs avec shifts) :
`document.documentElement.scrollWidth <= clientWidth` et aucun élément visible
au-delà du bord droit, à 360, 390, 768, 1024, 1280, 1366 et 1536 px, pour
chaque page modifiée. Dernier passage (finitions) : Utilisateurs, Servant(e)s,
Shifts, Changement, fiche d'un modèle de Shift (nom très long) et
Paramètres → Rôles.

Procédure : `playwright-core` installé hors du dépôt (`/tmp/pw`), base MySQL
jetable créée pour l'occasion puis supprimée, `public/hot` remis dans son état
initial, captures écrites hors du dépôt.
