# Tester tous les parcours sur STAGING

Ce guide est pour une personne non experte. Il explique :

1. comment créer des comptes de test (un par rôle) sur **staging** ;
2. quoi tester à la main, rôle par rôle, avec des cases à cocher ;
3. comment supprimer les comptes de test à la fin ;
4. ce qui a déjà été testé automatiquement (pour ne pas refaire pareil).

> **Règle d'or : tout se fait sur https://staging.daertech.ci. Jamais sur la production (shifts.daertech.ci).**
> La commande refuse de fonctionner en production, mais ne comptez pas là-dessus : vérifiez l'adresse dans votre navigateur avant de commencer.

---

## 1. Créer les comptes de test sur staging

### 1.1 Activer la commande (une seule fois, sur staging seulement)

1. Dans Plesk, ouvrez le site **staging.daertech.ci** puis le **Gestionnaire de fichiers**.
2. Ouvrez le fichier `.env` du site et ajoutez (ou modifiez) la ligne :
   `TEST_ACCOUNTS_ENABLED=true`
3. Enregistrez.
4. Ouvrez **Laravel Toolkit** (le site staging) puis **Artisan**, et lancez :
   `config:clear`

### 1.2 Créer les comptes

Dans Laravel Toolkit > Artisan, lancez :

`app:creer-comptes-test`

La commande affiche 5 lignes avec les adresses e-mail et un **mot de passe aléatoire** pour chacune.
**Les mots de passe ne sont affichés qu'une seule fois : copiez-les tout de suite** dans un endroit sûr (gestionnaire de mots de passe, note privée).

| Rôle testé | Adresse e-mail |
|---|---|
| Conseil du Temple (administrateur) | `test.conseil@staging.daertech.ci` |
| Secrétaire | `test.secretaire@staging.daertech.ci` |
| Coordonnateur | `test.coordonnateur@staging.daertech.ci` |
| Autres (lecture seule) | `test.autres@staging.daertech.ci` |
| Super Administrateur | `test.superadmin@staging.daertech.ci` |

Bon à savoir :

- Les comptes s'appellent « TEST … » pour ne jamais être confondus avec de vrais comptes.
- Aucun changement de mot de passe n'est exigé à la première connexion (pour faciliter les essais).
- Le **Coordonnateur** de test est rattaché au premier shift de l'organisation, pour qu'il voie son tableau de bord. S'il n'y a aucun shift, la commande en crée un nommé « TEST Shift de démonstration ».
- Relancer la commande ne recrée pas les comptes existants : elle le dit (« Déjà existant, conservé »).
- Mot de passe perdu : lancez `app:creer-comptes-test --reinitialiser` (nouveaux mots de passe pour les 5 comptes).
- Si la commande répond « Refusé » : vérifiez la ligne `TEST_ACCOUNTS_ENABLED=true` du `.env` de staging, puis relancez `config:clear`.

> Pour tester la partie **Propriétaire de plateforme** (licences, création d'organisation), utilisez votre compte habituel de propriétaire sur staging : il n'y a pas de compte de test pour ce rôle.

---

## 2. Listes de vérification par rôle

Mode d'emploi : ouvrez une **fenêtre de navigation privée** par rôle (ou déconnectez-vous entre deux rôles), connectez-vous, puis suivez les étapes. Cochez chaque case quand le résultat attendu est bien obtenu. Notez toute différence (capture d'écran + ce que vous avez fait).

Données utiles : pour les tests, créez vos propres servants avec un nom reconnaissable, par exemple « ESSAI Dupont ». Ne modifiez pas les vrais servants.

### 2.1 Conseil du Temple (`test.conseil@…`)

**Connexion et menus**

- [ ] Connexion avec le formulaire. Résultat : vous arrivez sur le **Tableau de bord**.
- [ ] Le menu de gauche contient : Tableau de bord, Shifts, Servant(e)s, Recommandés, Modèles de Shift, Recrutement, Changement, Rapports, Paramètres.
- [ ] Un **bandeau de licence** apparaît en haut si la licence expire bientôt (compte à rebours en jours).
- [ ] Ouvrir l'adresse `/parametres/roles` : résultat attendu, **page interdite (403)** (réservée au Super Administrateur).

**Servants**

- [ ] Servant(e)s > « Nouveau » : créer « ESSAI Dupont » (prénom, nom, genre). Résultat : la fiche s'ouvre, statut **Recommandé**.
- [ ] Sur la fiche, onglet **Situation** > « Changer le statut » : passer à **Nouveau**, puis **Ancien**, puis **Recommandé**. Chaque changement demande une confirmation. Résultat : le badge de statut change à chaque fois.
- [ ] Liste des servants : cliquer sur chaque **titre de colonne** (Nom, Prénom, Statut, Pieu…). Résultat : la liste se trie, une flèche indique le sens ; un second clic inverse le tri.
- [ ] Liste des servants : **faire glisser un titre de colonne** (poignée à gauche du titre) pour la déplacer. Résultat : l'ordre change et **reste le même après rechargement** de la page.
- [ ] Menu **Recommandés** : « ESSAI Dupont » y apparaît tant qu'il est Recommandé et en sort quand vous le passez Nouveau/Ancien.

**Relèves, réintégration, suppression définitive**

- [ ] Changement > « Nouvelle demande » > type **Relève** pour un servant affecté (ou utilisez une relève existante) puis ouvrez-la (« Traiter ») et enregistrez le résultat. Résultat : demande « Traitée ».
- [ ] Changement > « Servant(e)s relevé(e)s » : la ligne a un bouton **Réintégrer**. Cliquer, ajouter un commentaire, confirmer. Résultat : la ligne indique « Réintégré(e) le … » et le servant n'est plus relevé.
- [ ] Fiche d'un servant de test > onglet **Confidentialité** > « Supprimer définitivement » : essayer d'abord de confirmer **sans rien taper** (impossible), puis taper `SUPPRIMER` et confirmer. Résultat : le servant disparaît de la liste.

**Comptes (Paramètres > Utilisateurs)**

- [ ] « Nouveau compte » : créer « ESSAI Compte » avec le rôle **Secrétaire** et un mot de passe. Résultat : le compte apparaît dans la liste.
- [ ] Modifier ce compte et changer son **statut** : Recommandé, puis Nouveau, puis Ancien. Résultat : le statut affiché suit.
- [ ] Modifier ce compte, cocher **Accès suspendu**, enregistrer et confirmer. Ouvrir une autre fenêtre privée et essayer de s'y connecter. Résultat : **connexion refusée**.
- [ ] Décocher l'accès suspendu. Résultat : la connexion refonctionne.
- [ ] Menu d'actions (…) du compte lié à un servant : **Délier du servant(e)**. Résultat : le compte et la fiche existent toujours, mais ne sont plus liés.
- [ ] Supprimer le compte « ESSAI Compte ». Résultat : il disparaît ; si un servant lui était lié, sa fiche est conservée.
- [ ] Cliquer sur chaque titre de colonne de la liste des comptes. Résultat : le tri fonctionne.

**Paramètres et tris**

- [ ] Paramètres > Pieux : ajouter un pieu, le modifier, trier les colonnes.
- [ ] Paramètres > Horaires : ajouter, modifier, trier.
- [ ] Paramètres > Parcours : ajouter une étape, la modifier, trier.
- [ ] Modèles de Shift : créer un modèle, y ajouter un poste.
- [ ] Paramètres > **Licence** : la page indique l'organisation, la date d'expiration et le nombre de jours restants.
- [ ] Trier les tableaux de : Shifts, Recrutement, Changement, Relèves, Journal d'activité.

**Mobile (facultatif)**

- [ ] Sur téléphone (ou fenêtre étroite), les tableaux deviennent des cartes, sans défilement horizontal de la page.

### 2.2 Super Administrateur (`test.superadmin@…`)

- [ ] Refaire en accéléré les points « Servants » et « Comptes » du Conseil du Temple. Résultat : tout fonctionne de la même façon.
- [ ] Paramètres > **Rôles** : la page s'ouvre (contrairement au Conseil). Créer un rôle « ESSAI », le modifier, puis le supprimer.
- [ ] Les comptes Super Administrateur sont visibles dans Utilisateurs (le Conseil ne les voit pas).
- [ ] Essayer de suspendre **votre propre compte** : refusé avec un message.

### 2.3 Secrétaire (`test.secretaire@…`)

- [ ] Connexion. Résultat : vous arrivez directement sur la liste des **Servant(e)s**.
- [ ] Le menu ne contient que : Servant(e)s, Recommandés, Changement.
- [ ] Créer un servant « ESSAI Secrétaire ». Résultat : créé, statut **Recommandé**.
- [ ] Le modifier (changer le prénom). Résultat : enregistré.
- [ ] Sur sa fiche, onglet Situation : **pas de sélecteur de statut** ; pas d'onglet Confidentialité (donc pas de suppression).
- [ ] Changement > Nouvelle demande > **Permutation** (choisir un servant homme, un shift d'origine et un shift de destination). Résultat : la demande apparaît « En attente ».
- [ ] Nouvelle demande > **Relève**, puis l'ouvrir et enregistrer le résultat. Résultat : « Traitée ».
- [ ] Dans « Servant(e)s relevé(e)s » : **aucun bouton Réintégrer**.
- [ ] Trier les tableaux Servant(e)s, Recommandés, Changement, Relèves.
- [ ] Ouvrir les adresses `/parametres`, `/parametres/utilisateurs`, `/shifts`, `/rapports` : résultat attendu, **403** à chaque fois.

### 2.4 Coordonnateur (`test.coordonnateur@…`)

- [ ] Connexion. Résultat : tableau de bord de coordonnateur qui affiche **son shift**.
- [ ] Le menu ne contient que : Tableau de bord, Recrutement, Changement.
- [ ] Changement : la liste montre les permutations de ses shifts. Ouvrir une permutation en attente (« Traiter ») : boutons **Valider / Refuser** pour l'étape *origine* (si elle part de son shift) ou *destination* (si elle arrive dans son shift). Valider. Résultat : l'étape passe en « validée ».
- [ ] Il ne peut **pas** valider l'étape de l'autre shift (pas de bouton).
- [ ] Nouvelle demande : seule la **Permutation** est proposée. Le shift d'origine est le sien ; le shift de destination peut être n'importe quel shift. Résultat : la demande est créée.
- [ ] Recrutement : seul son shift est listé ; modifier le nombre à recruter. Résultat : enregistré.
- [ ] Ouvrir `/servants`, `/parametres`, `/rapports`, `/shifts`, `/transferts/releves` : **403**.
- [ ] Trier les tableaux Changement et Recrutement.

### 2.5 Autres, lecture seule (`test.autres@…`)

- [ ] Connexion. Résultat : tableau de bord en lecture.
- [ ] Menu : Tableau de bord, Shifts, Servant(e)s, Recommandés, Changement, Recrutement, Rapports. **Pas** de Paramètres ni de Modèles de Shift.
- [ ] Aucun bouton de création/modification : pas de « Nouvelle demande », pas de poignées de colonnes, pas de « Modifier » sur une fiche, pas de bouton « Réintégrer ».
- [ ] Ouvrir `/parametres`, `/parametres/utilisateurs`, `/parametres/licence`, `/servants/create`, `/shift-templates` : **403**.
- [ ] Rapports : l'export CSV des servants se télécharge.
- [ ] Profil : le nom et l'e-mail ne sont **pas modifiables** ; seul le **mot de passe** peut être changé.
- [ ] Trier les tableaux accessibles (Servants, Changement, Relèves, Shifts, Recrutement).

### 2.6 Propriétaire de plateforme (votre compte habituel)

- [ ] Connexion. Résultat : vous arrivez sur **Licences** (et non sur un tableau de bord d'organisation).
- [ ] Ouvrir `/servants` ou `/parametres` : **403**.
- [ ] « Nouvelle organisation » : créer « ESSAI Organisation » avec un administrateur et une date d'expiration. Résultat : une carte affiche l'e-mail et le **mot de passe temporaire une seule fois** (rechargez : il a disparu).
- [ ] Se connecter avec ce nouvel administrateur (fenêtre privée) : il doit **changer son mot de passe** avant de continuer.
- [ ] Modifier la licence de l'organisation d'essai avec une date **passée**. Se connecter avec son administrateur : bandeau « licence expirée », les pages se consultent mais **aucune modification n'est possible** (403).
- [ ] Remettre une date future : les modifications refonctionnent.
- [ ] Trier le tableau des licences.

### 2.7 Contrôles transversaux

- [ ] **Compte suspendu** : connexion refusée avec un message d'erreur (testé dans la section Conseil).
- [ ] **Isolation entre organisations** : si vous avez créé « ESSAI Organisation », vérifier que son administrateur ne voit **aucun** servant, shift ni compte de l'organisation principale (et inversement).
- [ ] **Thème sombre** : basculer clair/sombre (icône lune) : les pages restent lisibles.

---

## 3. À la fin : tout nettoyer

1. Nettoyer vos données d'essai (servants « ESSAI … », comptes, pieu/horaire/étape/modèle d'essai, organisation d'essai) depuis l'application.
2. Dans Laravel Toolkit > Artisan, lancez : `app:creer-comptes-test --supprimer`
   Résultat : les 5 comptes de test (et leurs liens avec les shifts) sont supprimés. Le shift « TEST Shift de démonstration » est supprimé s'il avait été créé par la commande et qu'il est resté vide.
3. Dans le `.env` de staging, remettez `TEST_ACCOUNTS_ENABLED=false` (ou supprimez la ligne), puis lancez `config:clear`.
4. Ne copiez **jamais** `TEST_ACCOUNTS_ENABLED=true` dans le `.env` de la production.

---

## 4. Ce qui a déjà été testé automatiquement

### 4.1 Tests PHPUnit (`php artisan test`)

Ils tournent sur une base de test jetable. Fichiers ajoutés pour ce chantier :

| Fichier | Ce qui est vérifié | Résultat |
|---|---|---|
| `CreerComptesTestTest` | La commande refuse sans le drapeau et en production ; crée les 5 comptes avec les bons rôles ; le mot de passe affiché permet la connexion ; idempotence ; `--reinitialiser` ; `--supprimer` (comptes et liens) ; le coordonnateur voit son shift | Réussi |
| `ParcoursCompletsConseilTest` | Cycle de vie d'un compte (création, statut Recommandé/Nouveau/Ancien, suspension et rétablissement, connexion refusée, délier, supprimer un compte lié) ; cycle d'un servant (statut, relève, réintégration, suppression définitive avec confirmation) ; colonnes déplaçables, tri serveur, section Licence ; pieu, horaire, étape, modèle ; Super Administrateur (rôles, comptes) | Réussi |
| `ParcoursCompletsRolesTest` | Matrice d'accès (13 pages × 5 rôles) ; Secrétaire de la création d'un servant à la décision finale d'une permutation validée par les deux coordonnateurs ; Coordonnateur (refus, périmètre, formulaire de permutation) ; Autres (aucune écriture, profil limité au mot de passe) | Réussi |
| `ParcoursCompletsPlateformeTest` | Propriétaire (redirection, création d'organisation, licence prolongée/expirée, lecture seule) ; compte suspendu (connexion refusée, session coupée) ; isolation entre organisations | Réussi |

Ces tests s'ajoutent à `ParcoursMetierTest` et aux tests existants (statuts, réintégration, suppression, rôle Autres, licence, etc.).

### 4.2 Tests dans un vrai navigateur (Chrome piloté par script)

Exécutés sur un serveur local avec une base jetable (supprimée ensuite), jamais sur staging ni sur la production. Chaque rôle se connecte par le formulaire. Les captures sont dans `/tmp/pw/captures/e2e-*.png` (poste de développement).

| Rôle | Parcours vérifiés | Contrôles | Résultat |
|---|---|---|---|
| Conseil du Temple | Menus, bandeau de licence, pages interdites, tri de 11 tableaux, créer un servant, changer son statut (avec confirmation), supprimer définitivement (saisie `SUPPRIMER`), réintégrer un servant relevé, créer/modifier un compte, statuts du compte, suspension et connexion refusée, rétablir, délier, supprimer un compte lié, pieu/horaire/étape/modèle, colonnes déplaçables (glisser-déposer + mémorisation), section Licence | 86 | Tous réussis |
| Super Administrateur | Mêmes parcours que le Conseil + page Rôles accessible | 87 | Tous réussis |
| Secrétaire | Menus, pages interdites, tris, créer/modifier un servant, pas de statut ni de suppression, créer une permutation et une relève, traiter la relève, pas de bouton Réintégrer | 38 | Tous réussis |
| Coordonnateur | Dashboard avec son shift, menus, pages interdites, tris, valider l'origine d'une permutation, créer une permutation vers un autre shift, recrutement limité à son shift | 25 | Tous réussis |
| Autres | Menus, pages interdites, tris de 6 tableaux, aucun bouton d'écriture, profil limité au mot de passe | 45 | Tous réussis |
| Propriétaire | Redirection vers les licences, pages métier interdites, tri, création d'organisation, identifiants affichés une seule fois, licence expirée puis prolongée, première connexion avec changement de mot de passe | 17 | Tous réussis |
| Mobile (360 px) | Aucun défilement horizontal sur 16 pages (tous rôles) | 16 | Tous réussis |

### 4.3 Anomalies trouvées pendant ces essais (corrigées)

- **Coordonnateur d'un seul shift** : le formulaire « Nouvelle demande » ne proposait que ses propres shifts pour l'origine **et** la destination, donc il ne pouvait jamais créer de permutation. Désormais la destination propose tous les shifts de l'organisation (l'origine reste limitée à ses shifts).
- **Date de la demande** vide dans « Nouvelle demande » alors qu'elle est obligatoire : elle est maintenant préremplie avec la date du jour.
- **Nouvelle organisation** : l'administrateur créé avait un « mot de passe temporaire » mais n'était pas obligé de le changer. Il doit maintenant le changer à la première connexion.

Remarque : en développement local, la console du navigateur signale des scripts « inline » bloqués par la politique de sécurité (CSP) : ils viennent de l'outil de développement Laravel Boost, absent en production. Sans conséquence.
