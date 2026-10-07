# Bascule vers la production (Railway → Plesk)

> Document de travail. **Aucun mot de passe, clé ou jeton réel ne doit être écrit ici ni ailleurs dans le dépôt : le dépôt GitHub est public.**
> Références : [`PLESK.md`](PLESK.md) (installation du staging, **section 10 : déploiement continu**), [`build-staging-branch.sh`](build-staging-branch.sh), [`dump-railway.sh`](dump-railway.sh), [`../.env.staging.example`](../.env.staging.example).

Notations utilisées :

- `shifts.daertech.ci` : domaine définitif (approuvé le 06/10/2026).
- `<RACINE_PROD>` : dossier du site dans Plesk (ex. `shift.daertech.ci/`) ; la racine web est `<RACINE_PROD>/public`.
- **Resp.** : **Dev** = Daniel (technique), **Plesk** = administrateur de l'hébergement / support Vename, **Conseil** = Conseil du Temple (référent métier).

---

## 1. Objectif et périmètre

**Objectif** : faire tourner l'application pour les vrais utilisateurs sur l'hébergement Plesk (`51.15.160.8`), sous un domaine définitif en HTTPS valide, et arrêter Railway une fois la nouvelle production stable.

**Dans le périmètre**

- Nouveau site Plesk de production, séparé du staging (base, utilisateur MariaDB, `.env`, `APP_KEY` distincts).
- Reprise des données : soit la base Railway, soit un réimport depuis l'Excel et les fichiers Word (voir décision D3).
- Comptes : propriétaire de plateforme, super administrateur, Conseil du Temple, éventuellement responsables (leaders).
- Bascule DNS, certificat, sauvegardes, surveillance, retour arrière.

**Hors périmètre**

- Évolutions fonctionnelles (gel du code pendant la bascule).
- Changement d'architecture : on garde **une seule base multi-organisations** (`organisation_id` partout). Un nouveau client = une nouvelle organisation, pas une nouvelle base.

**Rappels d'architecture utiles**

- Sessions, cache et file d'attente en base : **pas de worker** et **pas de planificateur** (impossible sur cet abonnement : interrupteur Laravel Toolkit « Scheduled Tasks » grisé).
- Photos des servants : fichiers dans `storage/app/private` (hors base) → à copier à part.
- Sauvegarde HTTP : `GET /system/backup` avec l'en-tête `X-Backup-Token` (variable `BACKUP_TOKEN` ; vide = route désactivée, 403).
- Migrations après déploiement : `POST /system/migrate` avec l'en-tête `X-Deploy-Token` (variable `DEPLOY_TOKEN` ; vide = route désactivée, 403), appelé par le pipeline GitHub (PLESK.md §10 c).
- Contraintes Plesk : pas de SSH, pas de Node, les actions post-déploiement Git n'ont pas accès à PHP. Tout passe par l'UI : **Git** (Pull / Deploy, mode Automatic), **Laravel Toolkit** (Artisan, variables d'environnement). Les migrations sont déclenchées par le pipeline GitHub via `POST /system/migrate`. `vendor/` est livré par la branche `staging` : PHP Composer de Plesk n'est plus utilisé.
- **Déploiement continu** : chaque push sur `master` aux tests verts met à jour **staging et production en même temps** (GitHub Actions → branche `staging` → webhooks Plesk). Détails pas à pas : [`PLESK.md`, section 10](PLESK.md#10-déploiement-continu-github-actions--plesk).

---

## 2. Décisions à prendre avant de commencer

| # | Question | Options | Recommandation |
|---|----------|---------|----------------|
| D1 | Domaine définitif | a) sous-domaine `daertech.ci` (ex. `shift.daertech.ci`) ; b) domaine dédié au client ; c) domaine racine | **a)** : DNS déjà chez Vename, même procédure que le staging. Garder un nom court et stable (il sera communiqué aux utilisateurs). |
| D2 | Base de production séparée du staging ? | a) même base ; b) base + utilisateur MariaDB dédiés | **b) obligatoire** : base dédiée (ex. `daertech_shift_prod`) avec **son propre utilisateur** et un mot de passe neuf. Le mot de passe du staging a été exposé : le changer aussi. |
| D3 | Origine des données | a) reprendre la base Railway (dump) ; b) réimporter depuis l'Excel + Word | **À confirmer d'abord : y a-t-il de vraies saisies d'utilisateurs sur Railway** (servants créés/modifiés, permutations, parcours, photos, comptes) ? Oui → **a)**. Non (seulement l'import initial et des essais) → **b)**, plus propre (pas de données de démonstration). |
| D4 | Envoi des e-mails (SMTP) | a) boîte Plesk `no-reply@<domaine>` ; b) fournisseur transactionnel (Brevo, Mailjet…) ; c) rester en `log` | **a)** pour démarrer (gratuit, déjà là). Sans SMTP, « mot de passe oublié » n'envoie rien (le lien finit dans `storage/logs`). Vérifier SPF/DKIM chez Vename pour éviter les spams. |
| D5 | Certificat HTTPS | a) Let's Encrypt via Plesk (si le support l'active) ; b) certificat acheté ailleurs et importé dans Plesk ; c) Cloudflare devant le site | **a)** si le support Vename/Plesk l'active, sinon **b)**. **Bloquant** : aucun utilisateur réel sans certificat valide (`SESSION_SECURE_COOKIE=true`). |
| D6 | Fenêtre de bascule | soir de semaine / week-end, hors activités du Temple | Un créneau de **3 h** où personne n'utilise l'appli, avec Dev et un référent Conseil disponibles. Annoncer le gel 48 h avant. |
| D7 | Branche déployée en production | a) même branche `staging` que le staging ; b) branche dédiée | **a)**, en **mode Automatic** (décision : staging et production se déploient ensemble à chaque push sur `master` aux tests verts). Conséquence : tester en local avant de pousser ; pendant un gel, ne pas pousser. |
| D8 | Comptes des responsables (leaders) | a) `temple:create-leader-accounts` ; b) création à la main par le Conseil | **a)** avec `--password=` (jamais le mot de passe par défaut) **ou b)** si peu de comptes. Les e-mails générés (`<chiffres>@shiftmanagement.local`) ne reçoivent pas de mail : pas de « mot de passe oublié » pour eux. |

---

### Décisions confirmées (05/10/2026)

- **D3 = b) réimport depuis les fichiers à jour** (décision du 06/10/2026) : le Temple transmet la dernière base (même format : Excel liste globale + 2 fichiers Word). **Elle remplace tout** : les saisies faites sur Railway sont abandonnées. Production = base vierge + `temple:import-roster` + `temple:import-history`. Un export Railway n'est plus qu'une précaution facultative.
- **Photos** : aucune dans un volume Railway, rien à copier.
- **Domaine définitif (D1) : `shifts.daertech.ci`**, approuvé le 06/10/2026. Le staging (`staging.daertech.ci`) sert de répétition jusqu'à la bascule.
- **Contexte** : Railway est abandonné à cause de son coût récurrent, qui augmente chaque mois. Conséquences : (1) sauvegarder la base Railway par précaution (facultatif, les fichiers à jour font foi) ; (2) ne plus pousser sur `master` inutilement, chaque push relance un build facturé ; (3) désactiver l'auto-déploiement Railway (Settings → Source → Disable) tant que Railway n'est qu'un secours.

## 3. Prérequis

- [ ] D1 à D8 tranchées et notées (date, qui a décidé).
- [ ] Réponse sur l'état réel des données Railway (D3).
- [ ] Certificat valide disponible ou engagement du support avec une date (D5).
- [ ] Accès Plesk avec droits : sous-domaine, bases, Git, PHP Composer, Laravel Toolkit, Gestionnaire de fichiers, Backup Manager.
- [ ] Accès au DNS chez Vename.
- [ ] Accès Railway (variables du service `shiftmanagement` : `BACKUP_TOKEN`, ou URL MySQL publique valide).
- [ ] Gestionnaire de mots de passe (ou coffre) prêt pour : `APP_KEY` prod, mot de passe MariaDB prod, `BACKUP_TOKEN` prod, `DEPLOY_TOKEN` staging et prod (distincts entre eux et du `BACKUP_TOKEN`), mot de passe SMTP, mots de passe temporaires des comptes.
- [ ] Poste local avec Git, Composer, Node, PHP (pour `php artisan key:generate --show` et, en secours, `build-staging-branch.sh`).
- [ ] Secrets GitHub `PLESK_WEBHOOK_STAGING` et `PLESK_WEBHOOK_PRODUCTION` créés (PLESK.md §10 b).
- [ ] Migrations par le pipeline (PLESK.md §10 c) : un jeton aléatoire par site (`openssl rand -hex 32`, jamais celui de `/system/backup`) mis dans le `.env` (`DEPLOY_TOKEN=`, puis `optimize:clear`) ; secrets GitHub `DEPLOY_TOKEN_STAGING` / `DEPLOY_TOKEN_PRODUCTION` (mêmes valeurs) ; variables GitHub `SITE_URL_STAGING` = `https://staging.daertech.ci` et `SITE_URL_PRODUCTION` = `https://shifts.daertech.ci`.
- [ ] Fichiers sources si D3 = b) : liste globale des servants (`.xlsx`), fiche des changements de shifts (`.docx`), liste des servants relevés (`.docx`), **dernières versions validées par le Conseil**.
- [ ] Liste des comptes à créer (nom, e-mail, rôle) validée par le Conseil.
- [ ] Message aux utilisateurs rédigé (nouvelle adresse, première connexion, contact en cas de souci).

---

## 4. Préparation (J-7 à J-1)

### J-7

- [ ] **DNS** : si `shifts.daertech.ci` existe déjà et pointe ailleurs, baisser son TTL à **300 s** chez Vename (il faut que l'ancien TTL expire avant J). Si c'est un nouveau sous-domaine, le créer dès maintenant vers `51.15.160.8` (permet de demander le certificat en avance).
- [ ] **Certificat** : ouvrir la demande au support (D5) pour `shifts.daertech.ci`.
- [ ] **Sécurité staging** : changer le mot de passe de l'utilisateur MariaDB du staging (Plesk > Bases de données > Utilisateurs), mettre à jour `DB_PASSWORD` dans le `.env` du staging, puis Laravel Toolkit : `config:clear`.
- [ ] **Répétition complète sur le staging** (ou sur un site Plesk temporaire) en suivant la section 5 à la lettre, **chronométrée**. Noter chaque blocage et corriger ce document.
- [ ] Tester `/system/backup` sur Railway (voir annexe A.3) : obtenir un dump exploitable **avant** J.

### J-2

- [ ] **Gel des modifications** : plus de push sur `master` sauf correctif bloquant. Prévenir les utilisateurs du créneau de bascule.
- [ ] Tests au vert en local : `php artisan test`.
- [ ] Branche à jour : dernier run **Deploy** vert dans GitHub > Actions ; vérifier sur GitHub que `staging` = dernier `master` + `public/build` + `vendor/`.
- [ ] Déployer cette version sur le staging et refaire le test de fumée (section 6).

### J-1

- [ ] Sauvegarde Railway complète (annexe A.3), fichier conservé **hors serveur et hors dépôt**.
- [ ] Générer en local l'`APP_KEY` de production : `php artisan key:generate --show` → la ranger dans le coffre (jamais dans Git). Elle doit être **différente** de celle du staging.
- [ ] Générer le `BACKUP_TOKEN` de production (`openssl rand -hex 32`) → coffre.
- [ ] Préparer le `.env` de production hors ligne (modèle `.env.staging.example`, valeurs en section 5 étape 3), sans le committer.
- [ ] Renommer les fichiers d'import **sans espaces ni accents** (ex. `roster.xlsx`, `changements.docx`, `releves.docx`).
- [ ] Confirmer la disponibilité de Dev, Plesk et Conseil sur le créneau.

---

## 5. Jour J pas à pas

Durées issues de l'estimation ; **remplacer par les durées mesurées lors de la répétition**.

| # | Étape | Durée | Resp. |
|---|-------|-------|-------|
| 0 | Annonce : « maintenance en cours » aux utilisateurs ; Railway reste en ligne mais **plus aucune saisie** | 5 min | Conseil |
| 1 | Sauvegarde finale Railway (si D3 = a) | 10 min | Dev |
| 2 | Site et base Plesk | 15 min | Dev/Plesk |
| 3 | `.env` de production | 10 min | Dev |
| 4 | Dépôt Git + Composer | 15 min | Dev |
| 5 | Données (import du dump **ou** seeders + imports Excel/Word) | 20–40 min | Dev |
| 6 | Mise en cache, lien storage | 5 min | Dev |
| 7 | Comptes | 15 min | Dev + Conseil |
| 8 | Test de fumée par rôle (section 6) | 30 min | Dev + Conseil |
| 9 | Go/No-Go | 5 min | Dev + Conseil |
| 10 | DNS / certificat / `APP_URL` | 15 min (+ propagation) | Dev/Plesk |
| 11 | Communication aux utilisateurs | 10 min | Conseil |

### Étape 1 — Sauvegarde finale Railway (si D3 = a)

- [ ] Vérifier qu'aucune saisie n'est en cours (créneau annoncé).
- [ ] Lancer l'export (annexe A.3) → `railway-dump-AAAAMMJJ-HHMM.sql.gz`.
- [ ] Ouvrir le fichier (après décompression) et vérifier qu'il contient bien les tables `users`, `servants`, `shifts`, `shift_transfer_requests`.
- [ ] Récupérer les photos de `storage/app/private` de Railway si elles existent (volume Railway) ; sinon, les fiches s'afficheront sans photo.

### Étape 2 — Site et base Plesk

- [ ] **Sites Web et domaines > Ajouter un sous-domaine** : `shifts.daertech.ci`, racine du document `<RACINE_PROD>/public`.
- [ ] **Paramètres PHP** : PHP **8.4** FPM, extensions `gd`, `zip`, `pdo_mysql`, `mbstring`, `xml`, `intl`.
- [ ] **Bases de données > Ajouter une base** : base dédiée, **utilisateur dédié** (pas celui du staging), mot de passe neuf rangé dans le coffre. Hôte `localhost`.

### Étape 3 — `.env` de production

Dans le Gestionnaire de fichiers, créer `<RACINE_PROD>/.env` (à la racine du projet, **pas** dans `public/`) à partir de `.env.staging.example`, en changeant :

- [ ] `APP_URL=https://shifts.daertech.ci`
- [ ] `APP_KEY=` la clé de production générée à J-1 (distincte du staging). Avec un dump Railway, une nouvelle clé ne casse aucune donnée (aucun champ chiffré en base) ; elle déconnecte seulement les sessions.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`
- [ ] `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` de la base de production
- [ ] `SESSION_SECURE_COOKIE=true` (ne pas désactiver « pour tester »)
- [ ] `BACKUP_TOKEN=` le jeton de production
- [ ] `DEPLOY_TOKEN=` le jeton de migration de production (valeur sans espace ; identique au secret GitHub `DEPLOY_TOKEN_PRODUCTION`, différent du `BACKUP_TOKEN`)
- [ ] Mail : `MAIL_MAILER=smtp` et `MAIL_*` si D4 est prêt ; sinon laisser `log` et le noter dans le suivi. `MAIL_FROM_ADDRESS` sur le domaine réel.
- [ ] Vérifier qu'il ne reste aucun `À_REMPLIR`.

### Étape 4 — Dépôt Git et Composer

- [ ] **Git > Ajouter un dépôt** : `https://github.com/danieleric05/shiftmanagement.git`, branche **`staging`**, chemin de déploiement **`<RACINE_PROD>`** (pas `public`), **mode Automatic** (D7). Ne pas mettre d'action post-déploiement (elles n'ont pas accès à PHP).
- [ ] Copier la **Webhook URL** du dépôt (réglages du dépôt Git) dans le secret GitHub `PLESK_WEBHOOK_PRODUCTION` (PLESK.md §10 a et b).
- [ ] **Pull now** puis **Deploy now**. Vérifier que `public/build/manifest.json` et `vendor/autoload.php` existent (plus besoin de PHP Composer : `vendor/` est dans la branche).
- [ ] Secret GitHub `DEPLOY_TOKEN_PRODUCTION` et variable `SITE_URL_PRODUCTION` créés : après chaque déploiement, le pipeline lance lui-même `migrate --force` via `POST /system/migrate` (PLESK.md §10 c). Pas de planificateur à activer.

### Étape 5 — Données (une seule des deux options)

**Option A — dump Railway (D3 = a)**

- [ ] **Bases de données > Importer un dump** sur la base de production avec le fichier de l'étape 1.
- [ ] Laravel Toolkit : `migrate --force` (n'applique que les migrations manquantes).
- [ ] **Aucun seeder.**
- [ ] Téléverser les photos dans `<RACINE_PROD>/storage/app/private/` (même arborescence que sur Railway).
- [ ] Contrôler qu'aucun compte de démonstration n'existe (ex. `admin@example.com`) ; le cas échéant, le supprimer depuis Paramètres > Utilisateurs.

**Option B — installation vierge + imports (D3 = b)**

- [ ] Laravel Toolkit, dans l'ordre (annexe A.1) : `migrate --force`, puis les 5 seeders de base : `RoleSeeder`, `OrganisationSeeder`, `WorkflowStepSeeder`, `ShiftTemplateSeeder`, `HoraireSeeder`.
- [ ] **Jamais** : `DatabaseSeeder`, `DevTestDataSeeder`, `PlatformOwnerSeeder`, `ShiftSeeder`, `AssignmentSeeder`, `PieuSeeder`.
- [ ] Créer le super administrateur **avant** l'import d'historique (la commande exige un administrateur ou super_admin dans l'organisation) : `app:create-super-admin <email>`.
- [ ] Téléverser les fichiers d'import dans un dossier hors `public/` (ex. `<RACINE_PROD>/storage/app/private/imports/`).
- [ ] `temple:import-roster` **à blanc** (sans `--force`), lire le résumé, puis avec `--force`.
  - Variante shifts seuls (sans servant(e)) : `temple:import-roster storage/app/private/imports/rooster.xlsx --shifts-seulement`, puis la même ligne avec `--force` (n'efface rien, relançable).
- [ ] `temple:import-history` **à blanc**, vérifier les noms non appariés, puis avec `--force`.
- [ ] **Supprimer les fichiers d'import** du serveur (données personnelles).

> Les commandes d'import ciblent par défaut **la première organisation** (`Organisation par défaut` créée par `OrganisationSeeder`). Pour une autre organisation, ajouter `--organisation=ID`.

### Étape 6 — Mise en cache

- [ ] Laravel Toolkit : `storage:link` (si refusé, non bloquant : les photos passent par PHP).
- [ ] **Ne pas** lancer `optimize` : avec le déploiement automatique, les caches de routes/config resteraient ceux de l'ancienne version (PLESK.md §10 g).
- [ ] Ouvrir `https://shifts.daertech.ci/up` (via l'IP/le fichier hosts si le DNS ne pointe pas encore) : page verte.

### Étape 7 — Comptes

- [ ] Propriétaire de plateforme : `app:create-super-admin <email> --platform-owner` → noter le mot de passe affiché **une seule fois** dans le coffre.
- [ ] Super administrateur (si pas déjà fait à l'étape 5) : `app:create-super-admin <email> --name=<Prenom>`.
- [ ] Compte(s) Conseil du Temple (rôle administrateur) : depuis Paramètres > Utilisateurs, connecté en super administrateur.
- [ ] Responsables (D8) : `temple:create-leader-accounts --password=<MotDePasseTemporaire>` **à blanc**, puis avec `--force`. Jamais sans `--password=`.
- [ ] Transmettre chaque mot de passe temporaire **par un canal privé** (pas de groupe WhatsApp collectif) ; changement à la première connexion.

### Étape 8 — Test de fumée

- [ ] Dérouler la section 6 pour **chaque rôle**. Toute case non cochée = No-Go ou correctif immédiat.

### Étape 9 — Go/No-Go

- [ ] Décision consignée (heure, qui). No-Go → section 7.

### Étape 10 — DNS, certificat, `APP_URL`

- [ ] Chez Vename : enregistrement A `shifts.daertech.ci` → `51.15.160.8` (si pas déjà fait à J-7).
- [ ] `nslookup shifts.daertech.ci` répond `51.15.160.8`.
- [ ] Certificat installé dans Plesk (SSL/TLS) + **redirection permanente 301 HTTP → HTTPS**. Cadenas sans avertissement dans un navigateur neuf.
- [ ] `APP_URL` = URL définitive dans `.env` ; si elle a changé : Laravel Toolkit `optimize:clear`.
- [ ] Connexion réelle depuis un téléphone sur le réseau mobile (hors Wi-Fi du bureau).

### Étape 11 — Communication

- [ ] Message aux utilisateurs : nouvelle adresse, identifiant, mot de passe temporaire envoyé à part, obligation de le changer, contact support.
- [ ] Sur Railway : prévenir que l'ancienne adresse n'est plus à utiliser (voir section 8 pour la désactivation).

---

## 6. Critères Go/No-Go et test de fumée par rôle

### Critères techniques (tous obligatoires)

- [ ] `https://shifts.daertech.ci/up` → 200.
- [ ] Certificat valide, pas d'alerte navigateur (sinon **No-Go** pour les utilisateurs réels).
- [ ] Page de connexion stylée (pas de 404 sur `/build/...`), titre d'onglet « Temple Shift Management ».
- [ ] Aucune erreur dans `storage/logs/laravel-AAAA-MM-JJ.log` pendant le test.
- [ ] Sauvegarde testée : un dump de la base de production a été produit et téléchargé (annexe A.4).
- [ ] Nombre de servants / shifts conforme à la source (Railway ou Excel), à 0 près.

### Propriétaire de plateforme

- [ ] Connexion, changement du mot de passe temporaire imposé.
- [ ] `/owner/licences` s'affiche, l'organisation du Temple est listée, licence active.

### Super administrateur

- [ ] Connexion + changement du mot de passe.
- [ ] Tableau de bord affiché.
- [ ] Paramètres > Rôles accessible.
- [ ] Paramètres > Utilisateurs : création d'un compte de test, puis suppression.

### Conseil du Temple (administrateur)

- [ ] Connexion, tableau de bord avec des chiffres cohérents.
- [ ] Liste des shifts, ouverture d'un shift, membres visibles.
- [ ] Recherche d'un servant par son nom : trouvé ; fiche ouverte, photo affichée (option A).
- [ ] Permutation : **création** d'une demande, **validation** côté origine et destination, puis **tranchée** (résolution) ; elle apparaît dans l'historique / relèves.
- [ ] Rapports : export **CSV servants** et **PDF remplissage des shifts** téléchargés et lisibles.
- [ ] Export d'une fiche servant.
- [ ] Journal d'activité : les actions du test y figurent.

### Coordonnateur

- [ ] Connexion, tableau de bord.
- [ ] « Mon shift » et ses servants uniquement (pas d'accès aux autres shifts, Paramètres → 403).
- [ ] Création d'une permutation pour un servant de son shift ; validation de sa partie.

### Secrétaire

- [ ] Connexion.
- [ ] Recherche et consultation des servants ; création d'un servant de test (puis suppression par le Conseil).
- [ ] Pas d'accès aux suppressions, exports, Paramètres (403 attendu).

### Mail

- [ ] « Mot de passe oublié » sur un compte à vraie adresse : e-mail reçu (si SMTP configuré). Sinon : noter la limite et prévenir le Conseil que la réinitialisation passe par un administrateur.

### Nettoyage après test

- [ ] Supprimer les données de test (servant, permutation, compte) ou les marquer clairement.

---

## 7. Retour arrière (rollback)

**Quand décider** : pendant la fenêtre, si un critère Go/No-Go est rouge et non corrigeable en 30 min ; après ouverture, si une erreur bloque une fonction essentielle (connexion, permutations) pour plusieurs utilisateurs et n'est pas corrigeable dans la journée. Décision : Dev + Conseil.

**Comment**

1. [ ] Avant la bascule DNS : rien à faire côté utilisateurs ; Railway reste l'adresse officielle. Annoncer la reprise sur Railway.
2. [ ] Après la bascule DNS : si `shifts.daertech.ci` est un nouveau nom, communiquer de nouveau l'adresse Railway (`shiftmanagement-production-4185.up.railway.app`). S'il remplaçait un ancien enregistrement, remettre l'ancienne valeur chez Vename ; grâce au TTL de 300 s, l'effet est quasi immédiat.
3. [ ] Exporter la base de production Plesk (Bases de données > Exporter un dump) **avant** toute autre action, pour garder les saisies faites entre-temps.
4. [ ] **Rattrapage des saisies** faites sur Plesk pendant la fenêtre : il n'y a pas de synchronisation automatique. Lister les actions depuis Paramètres > Journal d'activité (Plesk) et les ressaisir à la main sur Railway. D'où l'intérêt d'un créneau court et d'un gel des saisies.
5. [ ] Corriger, refaire une répétition sur le staging, replanifier.

**Ne pas supprimer Railway** (service et base) avant **J+30** au minimum, et pas avant que deux sauvegardes de production Plesk aient été testées par une restauration.

---

## 8. Après la bascule

### J+1

- [ ] Lire `storage/logs/laravel-AAAA-MM-JJ.log` : aucune erreur 500 / exception récurrente.
- [ ] Recueillir les retours du Conseil (connexion, lenteurs, données manquantes).
- [ ] Vérifier que les mots de passe temporaires ont été changés (comptes créés par `app:create-super-admin` : changement imposé ; comptes créés depuis `/owner` ou à la main : demander explicitement de changer le mot de passe dans le Profil).

### J+7

- [ ] Logs : `LOG_STACK=daily` + `LOG_DAILY_DAYS=14` → rotation automatique sur 14 jours. Contrôler l'espace disque.
- [ ] **Sauvegardes régulières** :
  - Plesk > **Backup Manager** : sauvegarde planifiée quotidienne du site + base, conservation 7 à 14 jours, et si possible copie vers un stockage externe (FTP / cloud).
  - et/ou téléchargement régulier depuis un poste via `/system/backup` (annexe A.4).
  - Les photos (`storage/app/private`) ne sont **pas** dans le dump SQL : les inclure dans la sauvegarde Plesk du site.
- [ ] **Test de restauration** d'une sauvegarde sur le staging.
- [ ] Retrait des accès provisoires : comptes de test, collaborateurs Plesk temporaires, clé de déploiement GitHub inutile, fichiers d'import ou dumps restés sur le serveur.
- [ ] Changer tout secret qui a transité par un chat ou un e-mail pendant la bascule.

### J+30

- [ ] Désactivation de Railway : dernière sauvegarde de la base Railway (archivée hors serveur), puis suppression ou arrêt du service et de la base, et de son `BACKUP_TOKEN`. L'auto-deploy Railway sur `master` doit déjà être coupé (Settings → Source → Disable) ; le workflow GitHub « Database backup » n'a plus de déclenchement nocturne.
- [ ] Mettre à jour `PLESK.md` et la mémoire projet : « production = Plesk ».

---

## 9. Ajouter une nouvelle organisation (instance) après la bascule

Une « instance » = une **organisation** dans la même base. Pas de nouvelle base, pas de préfixe de tables, pas de nouveau site Plesk.

1. [ ] Se connecter avec le compte **propriétaire de plateforme**.
2. [ ] `/owner/licences` → créer l'organisation : nom, nom et e-mail du responsable (Conseil du Temple), date d'expiration de licence (facultative).
3. [ ] Noter immédiatement l'e-mail et le **mot de passe aléatoire** affichés une seule fois ; les transmettre par canal privé, demander de changer le mot de passe dès la première connexion (non imposé automatiquement pour ce compte).
4. [ ] Le Conseil de la nouvelle organisation crée ses utilisateurs (Paramètres > Utilisateurs) et paramètre shifts, horaires, pieux, parcours.
5. [ ] Import éventuel de son fichier Excel : relever l'**ID** de l'organisation puis utiliser `--organisation=ID` sur **chaque** commande d'import (sinon elles visent la première organisation, celle du Temple). Toujours à blanc d'abord.
6. [ ] Vérifier l'étanchéité : un compte de la nouvelle organisation ne voit aucun servant/shift du Temple.

---

## 10. Risques et mitigations

| Risque | Impact | Mitigation |
|--------|--------|------------|
| Pas de certificat valide à J | Connexion impossible (cookies sécurisés) ou alertes qui font fuir les utilisateurs | Demande au support dès J-7 ; certificat acheté (D5 b) en plan B ; **pas de bascule sans certificat** |
| Secret commité dans le dépôt public | Compromission de la base / des comptes | `.env` hors Git ; aucun secret dans ce document ; vérifier `git status` avant chaque commit ; changer le secret immédiatement s'il fuit |
| Mot de passe MariaDB du staging déjà exposé | Accès à la base staging | Le changer (J-7) ; utilisateur dédié par base ; jamais réutilisé en production |
| `APP_KEY` perdue | Sessions et liens de réinitialisation invalides, impossible de reconstruire l'environnement à l'identique | Clé de production propre, rangée dans le coffre hors serveur |
| Lancer un seeder de démonstration | Compte `admin@example.com` / mot de passe connu, données factices | Liste interdite (section 5) ; contrôle des comptes après installation |
| `temple:create-leader-accounts` sans `--password=` | Tous les leaders avec un mot de passe par défaut connu (dans le code public) | Toujours `--password=` ; changement imposé à la première connexion |
| Import Railway impossible (1045 en connexion directe) | Pas de reprise de données | Passer par `/system/backup` (annexe A.3) ; à valider dès J-7 |
| Collations MySQL 8 inconnues de MariaDB | Échec de l'import du dump | `dump-railway.sh` convertit les collations ; tester l'import en répétition |
| Import Excel/Word : noms mal appariés | Historique incomplet | Essai à blanc, lecture des non-appariés, correction des fichiers source avant `--force` |
| Composer « Update » au lieu d'« Install » | Versions non testées en production | `vendor/` est construit par GitHub Actions depuis `composer.lock` ; ne plus utiliser PHP Composer de Plesk |
| Fichiers déployés avant la migration (quelques secondes, jusqu'à l'appel de `POST /system/migrate` par le pipeline) | Erreurs 500 passagères sur les pages qui utilisent une nouvelle colonne/table | Migrations additives ; pousser hors des heures d'utilisation ; si le run Deploy est rouge : le relancer ou `migrate --force` dans Laravel Toolkit > Artisan |
| Fuite du jeton `DEPLOY_TOKEN` | Un tiers peut lancer `migrate --force` (seulement les migrations du dépôt, 6 fois/min max) | Jeton long et distinct par site ; en cas de doute, le changer dans le `.env` et le secret GitHub ; vide = endpoint désactivé |
| Branche `staging` lourde (`vendor/` ≈ 70 Mo, ≈ 8 000 fichiers) | Pull/Deploy Plesk plus longs, dépôt qui grossit | Objets Git dédupliqués entre builds ; surveiller la durée du déploiement et l'espace disque |
| Webhook Plesk en échec ou secret absent | Un site n'est pas mis à jour (versions différentes entre staging et production) | Run « Deploy » en erreur/avertissement dans GitHub Actions ; Pull now + Deploy now à la main |
| Push sur `master` pendant le gel | Déployé immédiatement en staging **et** en production | Gel annoncé ; aucun push sans tests locaux ; retour arrière par `git revert` + push (PLESK.md §10 f) |
| E-mails non configurés | « Mot de passe oublié » inopérant | D4 ; à défaut, réinitialisation par un administrateur |
| Saisies perdues en cas de rollback | Données à ressaisir | Fenêtre courte, gel des saisies, journal d'activité pour rattraper |
| Pas de sauvegarde réellement restaurable | Perte définitive | Backup Manager planifié + test de restauration à J+7 |
| Titre de l'appli figé à la compilation | Mauvais nom affiché si on veut le changer | Modifier `VITE_APP_NAME` dans `.github/workflows/deploy.yml` (ou `VITE_APP_NAME="Autre nom" bash deploy/build-staging-branch.sh` en secours) puis redéploiement |

---

## 11. Annexe — commandes exactes

### A.1 Laravel Toolkit (Artisan)

Règles de saisie :

- Taper **sans** `php artisan` devant : `migrate --force`, pas `php artisan migrate --force`.
- **Pas de guillemets imbriqués**. Fichiers renommés sans espaces pour ne pas en avoir besoin.
- **Vider entièrement le champ** avant chaque commande : il garde parfois l'ancienne commande ou l'ancien résultat, qui se retrouve collé à la nouvelle.
- Pas de caractère parasite en fin de ligne (erreur déjà vue : `--force~`). Pas de `#` ni de commentaire dans le champ.
- Vérifier le chemin des fichiers : relatif à la racine du projet (`<RACINE_PROD>`).

Migrations et seeders de base (option B) :

```text
migrate --force
db:seed --class=RoleSeeder --force
db:seed --class=OrganisationSeeder --force
db:seed --class=WorkflowStepSeeder --force
db:seed --class=ShiftTemplateSeeder --force
db:seed --class=HoraireSeeder --force
```

Comptes :

```text
app:create-super-admin prenom.nom@exemple.com --name=Prenom
app:create-super-admin proprietaire@exemple.com --platform-owner
```

Imports (à blanc, puis la même ligne avec `--force`) :

```text
temple:import-roster storage/app/private/imports/roster.xlsx
temple:import-roster storage/app/private/imports/roster.xlsx --force
temple:import-roster storage/app/private/imports/rooster.xlsx --shifts-seulement
temple:import-roster storage/app/private/imports/rooster.xlsx --shifts-seulement --force
temple:import-history storage/app/private/imports/changements.docx storage/app/private/imports/releves.docx --date-defaut=2026-10-01
temple:import-history storage/app/private/imports/changements.docx storage/app/private/imports/releves.docx --date-defaut=2026-10-01 --force
temple:create-leader-accounts --password=MOT_DE_PASSE_TEMPORAIRE
temple:create-leader-accounts --password=MOT_DE_PASSE_TEMPORAIRE --force
```

- `--date-defaut=` : date appliquée aux changements de shift sans date (AAAA-MM-JJ ; par défaut : la date du jour). Adapter la valeur.
- `temple:import-roster` **remplace** les shifts/servants existants de l'organisation ; `--keep-existing` ajoute sans toucher à l'existant.
- `--shifts-seulement` : crée **uniquement les 20 shifts** du fichier (rattachés au modèle de shift), sans servant(e), affectation, pieu, besoin de recrutement ni permutation. Ne supprime rien, relançable sans doublon. À utiliser à la place de l'import complet si les servant(e)s seront saisi(e)s dans l'application.
- Autre organisation : ajouter `--organisation=ID` à chaque commande d'import.
- Si des shifts de démonstration traînent : `temple:remove-demo-shifts` (à blanc) puis `temple:remove-demo-shifts --force`.

Caches (après déploiement ou modification du `.env`) :

```text
storage:link
optimize:clear
```

(Plus de `optimize` : le déploiement est automatique, voir PLESK.md §10 g.)

### A.2 Poste local

```bash
php artisan test                      # tests avant la bascule
php artisan key:generate --show       # APP_KEY de production (à ranger dans le coffre)
openssl rand -hex 32                  # BACKUP_TOKEN de production
bash deploy/build-staging-branch.sh   # SECOURS : reconstruit et pousse staging (front + vendor), puis Pull/Deploy dans Plesk
```

Changer le titre de l'appli (figé à la compilation, défaut « Temple Shift Management ») :

```bash
VITE_APP_NAME="Autre nom" bash deploy/build-staging-branch.sh
```

### A.3 Export de la base Railway (sur votre poste, jamais dans le dépôt)

Connexion directe refusée (`1045`) → passer par l'endpoint de l'application :

```bash
export BACKUP_URL='https://shiftmanagement-production-4185.up.railway.app/system/backup'
export BACKUP_TOKEN='<valeur de BACKUP_TOKEN dans les variables Railway>'
bash deploy/dump-railway.sh
```

Ou, si une URL MySQL publique valide est disponible :

```bash
export MYSQL_PUBLIC_URL='mysql://<user>:<motdepasse>@<hote>:<port>/railway'
bash deploy/dump-railway.sh
```

Résultat : `railway-dump-AAAAMMJJ-HHMM.sql.gz` (droits 600), collations adaptées à MariaDB. Décompresser si l'import Plesk refuse le `.gz`. **Ne jamais le committer.** Penser à `unset BACKUP_TOKEN MYSQL_PUBLIC_URL` ensuite.

### A.4 Sauvegarde de la production via `/system/backup`

```bash
curl -fsS -H "X-Backup-Token: <BACKUP_TOKEN_PROD>" https://shifts.daertech.ci/system/backup -o prod-$(date +%Y%m%d).sql
```

Limité à 5 appels par minute. Le fichier contient toutes les données : le stocker chiffré, hors serveur.

### A.5 Erreurs fréquentes déjà rencontrées

| Symptôme | Cause | Correction |
|----------|-------|------------|
| Option inconnue `--force~` | Caractère parasite collé | Vider le champ, retaper la commande |
| Commande incohérente / ancien texte en tête | Champ Toolkit prérempli avec l'ancienne commande ou l'ancien résultat | Tout sélectionner, effacer, retaper |
| Commande tronquée ou ignorée | `#` ou commentaire dans le champ | Ne coller que la commande |
| « Page expirée » à la connexion | Site en HTTP ou certificat invalide avec `SESSION_SECURE_COOKIE=true` | Installer un certificat valide, forcer HTTPS |
| Page sans style, 404 sur `/build/...` | Branche `master` déployée au lieu de `staging`, ou `staging` pas reconstruite | GitHub > Actions > Deploy > Run workflow (ou `build-staging-branch.sh`), puis Pull now + Deploy now sur `staging` |
| Erreur 500 juste après un déploiement, « column/table not found » dans les logs | Migration pas passée (il n'y a plus de planificateur) | Relancer le workflow **Deploy** (GitHub > Actions > Run workflow) ou lancer `migrate --force` dans Laravel Toolkit > Artisan |
| « Service indisponible » | Site resté en maintenance | Supprimer `storage/framework/down` |
| Modification du `.env` sans effet | Configuration en cache | `optimize:clear` |
| `Introuvable : le rôle super_admin et une organisation` | Seeders de base non lancés | `RoleSeeder` puis `OrganisationSeeder` |
| `Aucun administrateur trouvé…` (import historique) | Pas de super_admin/administrateur dans l'organisation | `app:create-super-admin` avant l'import |
