# Déployer ShiftManagement sur Plesk (staging)

Cible : **https://staging.daertech.ci** — hébergement mutualisé Plesk, IP `51.15.160.8`.
Tout se fait depuis l'interface Plesk ; SSH n'est pas nécessaire.

## 1. DNS

Chez le fournisseur DNS du domaine `daertech.ci` (là où sont gérés ses enregistrements) :

| Type | Nom       | Valeur        | TTL  |
|------|-----------|---------------|------|
| A    | `staging` | `51.15.160.8` | 3600 |

Attendre la propagation (de quelques minutes à quelques heures) : `nslookup staging.daertech.ci` doit répondre `51.15.160.8`.
Si le DNS de `daertech.ci` est géré par Plesk lui-même, l'enregistrement est créé automatiquement avec le sous-domaine.

## 2. Sous-domaine, PHP et base de données

1. **Sites Web et domaines > Ajouter un sous-domaine** : `staging.daertech.ci`, racine du document **`staging.daertech.ci/public`**.
2. **Paramètres PHP** du sous-domaine : version **8.3 ou plus** (FPM). Extensions requises : `gd`, `zip`, `pdo_mysql`, `mbstring`, `xml`, `intl` (en général actives par défaut).
3. **Bases de données > Ajouter une base** : noter le nom, l'utilisateur et le mot de passe (hôte `localhost`).

## 3. HTTPS (Let's Encrypt)

Une fois le DNS propagé : **SSL/TLS > Let's Encrypt** sur `staging.daertech.ci`, puis activer **« Redirection permanente 301 de HTTP vers HTTPS »**.
Obligatoire : le `.env` impose des cookies de session sécurisés (`SESSION_SECURE_COOKIE=true`) ; en HTTP la connexion échouerait (« page expirée »).
L'appli fait déjà confiance au proxy nginx de Plesk (`trustProxies('*')`), donc les URLs générées par Laravel/Inertia sont bien en `https://`.

## 4. Fichier `.env` (une seule fois)

1. En local, générer une clé : `php artisan key:generate --show` (ou **reprendre l'APP_KEY de Railway** si vous importez ses données).
2. Dans le **Gestionnaire de fichiers**, dans `staging.daertech.ci/`, créer `.env` à partir de [`.env.staging.example`](../.env.staging.example) et remplacer chaque `À_REMPLIR` (DB_*), coller `APP_KEY`.
3. Le `.env` n'est pas dans Git : les déploiements suivants ne le touchent pas.

## 5. Dépôt Git

**Git > Ajouter un dépôt** :

- Dépôt distant : `https://github.com/danieleric05/shiftmanagement.git` (dépôt privé : utiliser l'URL SSH `git@github.com:danieleric05/shiftmanagement.git` et ajouter la clé SSH affichée par Plesk dans GitHub > Settings > Deploy keys, lecture seule).
- Branche : `master` (ou `staging` si Node.js est absent, voir plus bas) — Mode : **automatique** — Chemin de déploiement : **`staging.daertech.ci`** (la racine du projet, pas `public`).
- Pour le déploiement automatique à chaque push sur `master` : voir la section **10. Déploiement continu** (l'URL de webhook Plesk va dans un **secret GitHub Actions**, pas dans GitHub > Webhooks).

**Actions de déploiement supplémentaires** — uniquement si elles ont accès à PHP (ce n'est **pas** le cas sur l'hébergement actuel : laisser ce champ vide et suivre la section 10). Sinon, coller exactement :

> **Méthode d'origine, non utilisée sur l'hébergement actuel.** Les actions post-déploiement de Plesk n'ont pas accès à PHP sur cet hébergement : le script `deploy/plesk-deploy.sh` ne s'y exécute pas. Le déploiement réel passe par le pipeline de la section 10 (branche `staging` avec `vendor`, webhooks Plesk, migrations déclenchées par le pipeline via `POST /system/migrate`). Le script reste utile sur un serveur où PHP est disponible en ligne de commande.

```bash
PHP_BIN=/opt/plesk/php/8.3/bin/php bash deploy/plesk-deploy.sh
```

(Adapter `8.3` à la version choisie à l'étape 2.) Le script installe les dépendances PHP sans paquets de dev, vérifie `.env`/`APP_KEY`, compile les assets si `npm` est disponible, passe le site en maintenance le temps des migrations (remise en ligne garantie même en cas d'erreur), crée `public/storage`, règle les permissions et met en cache config/routes/vues. Il **n'exécute aucun seeder**. On peut le relancer sans risque (bouton « Déployer » de Plesk).

> **Laravel Toolkit** : si l'extension est présente, elle peut afficher le site comme application Laravel et exécuter des commandes Artisan depuis l'interface (utile sans SSH). Ne pas activer en plus son propre déploiement automatique : le script ci-dessus s'en charge déjà.

### Assets front (`public/build`) — important

`public/build` est dans `.gitignore` : **le dépôt Git ne contient pas les fichiers CSS/JS compilés**. Donc :

- **Node.js disponible dans Plesk** (extension Node.js, version 20.19+ ou 22+) : le script lance `npm ci && npm run build` lui-même. Rien à faire.
- **Node.js absent** : utiliser la branche `staging` (ci-dessous).

### Front compilé sans Node : branche `staging`

La branche `staging` = `master` + `public/build` compilé (versionné uniquement sur cette branche ; `.gitignore` de `master` inchangé). Dans Plesk, le dépôt Git doit pointer sur la branche **`staging`** et la commande de déploiement être précédée de `SKIP_NPM=1` :

```bash
SKIP_NPM=1 PHP_BIN=/opt/plesk/php/8.3/bin/php bash deploy/plesk-deploy.sh
```

La branche `staging` contient aussi `vendor/` (dépendances PHP de production) et `bootstrap/cache/packages.php` : Plesk n'a plus besoin de Composer.

Mise à jour : **automatique** à chaque push sur `master` (section 10). Méthode manuelle de secours (même résultat) :

1. En local (PHP, Node + Composer requis, arbre de travail propre) : `bash deploy/build-staging-branch.sh`. Le script recrée `staging` depuis `origin/master` dans un worktree temporaire, compile le front sans `.env`, commite `public/build` + `vendor/` et pousse `staging` (jamais `master`).
2. Dans Plesk > **Git** : **Pull now** puis **Deploy now** sur la branche `staging`, sur **chaque** site (staging et production).

## 6. Données

### Option A (recommandée) — reprendre les données de Railway

À faire **avant le premier déploiement** (sinon : vider la base Plesk puis importer).

1. Exporter la base Railway, au choix :
   - depuis votre poste, via le proxy TCP public de la base Railway (onglet *Connect*) :
     `mysqldump -h <HÔTE_RAILWAY> -P <PORT> -u <USER> -p --single-transaction --no-tablespaces <BASE> > railway.sql`
   - ou via la route de sauvegarde de l'appli Railway, si `BACKUP_TOKEN` y est défini :
     `curl -H "X-Backup-Token: <TOKEN>" https://<URL_RAILWAY>/system/backup -o railway.sql`
2. Plesk > **Bases de données > Importer un dump** (ou phpMyAdmin > Importer) avec `railway.sql`.
3. Mettre dans le `.env` Plesk **la même `APP_KEY` que sur Railway**.
4. Photos des servants : elles sont dans `storage/app/private/` de l'appli Railway. Si elles existent (volume Railway), les récupérer et les téléverser au même endroit ; sinon les fiches s'afficheront sans photo.
5. Lancer le déploiement : `migrate` n'applique que les migrations manquantes. **Aucun seeder à lancer.**

### Option B — installation vierge

Après le premier déploiement, exécuter **une seule fois** (Laravel Toolkit > Artisan) :

```bash
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=OrganisationSeeder --force
php artisan db:seed --class=WorkflowStepSeeder --force
php artisan db:seed --class=ShiftTemplateSeeder --force
php artisan db:seed --class=HoraireSeeder --force
```

Les migrations ne créent que le rôle `secretaire` : **`RoleSeeder` est indispensable** (super_admin, administrateur, coordonnateur_equipe). Ces seeders sont idempotents.

**Ne jamais lancer en production** : `DatabaseSeeder` (crée `admin@example.com` / mot de passe `password` et des shifts de démo), `DevTestDataSeeder`, `ShiftSeeder`, `AssignmentSeeder`, `PieuSeeder` (pieux d'exemple). `PlatformOwnerSeeder` ne fonctionne pas non plus en production (il dépend de Faker, absent avec `--no-dev`). Créer le premier compte avec la commande dédiée (après `RoleSeeder` et `OrganisationSeeder`) :

```bash
php artisan app:create-super-admin adresse@exemple.com
```

Elle affiche **une seule fois** un mot de passe temporaire, à changer obligatoirement à la première connexion. Options : `--name=Prenom` (nom affiché), `--platform-owner` (compte propriétaire de plateforme, sans organisation ni rôle).

Pour créer **uniquement les 20 shifts** (Frères/Sœurs × mardi..samedi × matin/soir), sans aucun(e) servant(e), après `ShiftTemplateSeeder` : aperçu, puis application.

```bash
php artisan temple:import-roster storage/app/private/imports/rooster.xlsx --shifts-seulement
php artisan temple:import-roster storage/app/private/imports/rooster.xlsx --shifts-seulement --force
```

Ce mode ne supprime rien et peut être relancé sans créer de doublons. Les shifts sont rattachés au modèle de shift (postes proposés à l'affectation) ; aucun servant, affectation, pieu, besoin de recrutement ni permutation n'est créé.

## 7. Cron et files d'attente

- **Planificateur (cron)** : **aucun**. L'appli ne planifie aucune tâche, et l'abonnement ne le permet pas (pas de SSH ; l'interrupteur Laravel Toolkit « Scheduled Tasks » est grisé faute de la permission « Scheduler management »). Les migrations d'un nouveau déploiement sont **déclenchées par le pipeline** via `POST /system/migrate` (section 10 c).
- **Worker de file** : **inutile**. Les notifications passent par le canal `database` de façon synchrone et rien n'implémente `ShouldQueue`. L'e-mail « mot de passe oublié » part aussi en direct (et reste dans les logs tant que `MAIL_MAILER=log`).

## 8. Vérifications après déploiement

1. Le journal de déploiement Git de Plesk se termine par `Déploiement terminé.`
2. `https://staging.daertech.ci/up` affiche une page verte (200).
3. Page de connexion avec le style correct (CSS/JS chargés, pas d'erreur 404 sur `/build/...`), cadenas HTTPS sans avertissement.
4. Connexion, navigation dans les shifts, une photo de servant s'affiche (option A).
5. Pas d'erreur dans `storage/logs/laravel-AAAA-MM-JJ.log`.
6. Si la page affiche « Service indisponible » : supprimer `storage/framework/down` dans le Gestionnaire de fichiers.

## 9. Retour arrière

- **Avant chaque déploiement sensible** : exporter la base (Bases de données > Exporter un dump, ou Sauvegarde Plesk).
- **Code** : sur GitHub, `git revert` du commit fautif puis push sur `master` (ou redéployer depuis Plesk après le revert). Plesk redéploie alors l'ancienne version et relance le script.
- **Base** : si une migration a abîmé les données, réimporter le dump exporté juste avant. Éviter `migrate:rollback` sans avoir lu la migration concernée.
- **Site bloqué en maintenance** : supprimer `storage/framework/down`.
- **Mauvaise config en cache** après modification du `.env` : relancer le déploiement (il refait `config:cache`).

## 10. Déploiement continu (GitHub Actions → Plesk)

**Staging et production se mettent à jour en même temps**, automatiquement, à chaque push sur `master` dont les tests passent. Il n'y a plus d'étape « staging d'abord » : pour essayer une modification sans la mettre en production, la tester en local avant de pousser.

### Le flux complet

1. Push sur `master` → le workflow **Tests** tourne (Pint, PHPStan, tests).
2. Tests verts → le workflow **Deploy** (`.github/workflows/deploy.yml`) démarre : `composer install --no-dev`, `npm run build`.
3. Il recrée la branche **`staging`** = commit testé + `public/build` + `vendor/`, et la pousse (force).
4. Il appelle les deux webhooks Plesk (secrets GitHub).
5. Chaque site Plesk (mode **Automatic**) tire `staging` et copie les fichiers.
6. Le pipeline attend que chaque site serve le nouveau build (`/build/manifest.json` et `/build/deploy-id.txt`, 6 min max), puis appelle `POST https://<site>/system/migrate` avec son jeton : le site lance `migrate --force` puis `optimize:clear`. Le run échoue si un site n'est pas à jour à temps ou si l'appel ne répond pas 200.

Tests rouges → rien n'est déployé. Si un push plus récent arrive pendant les tests, seul le plus récent est déployé.

### a) Plesk : mode Automatic et URL du webhook (sur chacun des deux sites)

1. Plesk > **Sites Web et domaines** > le site (`staging.daertech.ci`, puis `shifts.daertech.ci`) > **Git**.
2. Ouvrir les **réglages du dépôt** (icône engrenage / « Repository Settings »).
3. Vérifier : branche **`staging`**, chemin de déploiement = racine du projet (pas `public`), **aucune action de déploiement supplémentaire**.
4. Mode de déploiement : **Automatic**. Enregistrer.
5. Dans ces mêmes réglages, Plesk affiche **« Webhook URL »** : la copier (bouton copier). **Cette URL est un secret** : ne jamais la coller dans un fichier du dépôt, un ticket ou un chat.
6. PHP Composer de Plesk : plus nécessaire (`vendor/` arrive par Git). Ne plus cliquer sur **Install**/**Update**.

### b) GitHub : les deux secrets

GitHub > dépôt > **Settings** > **Secrets and variables** > **Actions** > **New repository secret** :

| Nom | Valeur |
|-----|--------|
| `PLESK_WEBHOOK_STAGING` | Webhook URL du site `staging.daertech.ci` |
| `PLESK_WEBHOOK_PRODUCTION` | Webhook URL du site `shifts.daertech.ci` |

Un secret absent ou vide n'empêche pas le build : le run affiche un avertissement jaune et ce site n'est pas notifié (le déployer à la main : Plesk > Git > **Pull now** puis **Deploy now**). Les URL n'apparaissent jamais dans les logs.

### c) Migrations déclenchées par le pipeline (`POST /system/migrate`)

Plesk Git ne peut pas lancer PHP, et le planificateur Laravel ne peut pas tourner (pas de SSH ; interrupteur Laravel Toolkit « Scheduled Tasks » grisé). Après chaque déploiement, **le pipeline appelle lui-même** `POST /system/migrate` sur chaque site. L'endpoint est protégé par un jeton (en-tête `X-Deploy-Token`), limité à 6 appels par minute et par IP, et refuse deux exécutions simultanées. Sans jeton configuré, il est désactivé (403).

À faire **une fois par site** (staging, puis production), avec **un jeton différent par site** :

1. **Générer un jeton** long et aléatoire, en local : `openssl rand -hex 32` (ou `php -r "echo bin2hex(random_bytes(32));"`). Ne **jamais** réutiliser le jeton `BACKUP_TOKEN` de `/system/backup`.
2. **Plesk** > le site > **Laravel** (Laravel Toolkit) > **Environment variables** > **Edit** : ajouter `DEPLOY_TOKEN=<le jeton>` (valeur sans espace ni guillemets). Enregistrer.
3. Laravel Toolkit > **Artisan** : lancer `optimize:clear`.
4. **GitHub** > dépôt > **Settings** > **Secrets and variables** > **Actions** :
   - onglet **Secrets** > **New repository secret** : `DEPLOY_TOKEN_STAGING` (jeton du site staging) et `DEPLOY_TOKEN_PRODUCTION` (jeton du site de production), mêmes valeurs qu'à l'étape 2 ;
   - onglet **Variables** > **New repository variable** : `SITE_URL_STAGING` = `https://staging.daertech.ci` et `SITE_URL_PRODUCTION` = `https://shifts.daertech.ci`.
5. Vérifier : GitHub > **Actions** > **Deploy** > **Run workflow**. L'étape « Lancer les migrations sur chaque site » affiche `STAGING : migrated=false` (ou `true`) puis la sortie d'Artisan.

Si une variable ou un secret manque, le run affiche un avertissement jaune et ne migre pas ce site : lancer alors `migrate --force` dans Laravel Toolkit > Artisan.

L'hébergeur (Vename) pourrait aussi activer la permission « Scheduler management » (l'interrupteur Toolkit « Scheduled Tasks ») : **ce n'est plus nécessaire**. Ne pas activer **Queue** : aucune file n'est utilisée.

**Délai possible** : entre la copie des fichiers par Plesk et l'appel de migration, quelques secondes s'écoulent. Pendant cet intervalle, une page qui utilise une nouvelle colonne ou table peut renvoyer une erreur 500. Pour limiter le risque : écrire des migrations « additives » (ajouter des colonnes/tables, ne pas renommer ni supprimer dans le même push que le code qui en dépend) et pousser de préférence hors des heures d'utilisation. **Erreur 500 juste après un déploiement** : relancer le workflow **Deploy** (GitHub > Actions > Run workflow) ou lancer `migrate --force` dans Laravel Toolkit > Artisan.

### d) Railway

Railway est abandonné : désactiver son auto-déploiement sur `master` (Railway > service > **Settings** > **Source** > **Disable**), sinon chaque push relance un build facturé. Le workflow GitHub « Database backup » ne tourne plus la nuit (lancement manuel seulement).

### e) Relancer un déploiement à la main

- **Tout le pipeline** : GitHub > **Actions** > **Deploy** > **Run workflow** (branche `master`). Il reconstruit `staging` depuis la pointe de `master` (sans relancer les tests), rappelle les webhooks et relance les migrations.
- **Un seul site** : Plesk > Git > **Pull now** puis **Deploy now**.
- **Sans GitHub Actions** : `bash deploy/build-staging-branch.sh` puis Pull/Deploy dans Plesk (section 5).

### f) Retour arrière

1. En local : `git revert <commit fautif>` puis `git push` sur `master`.
2. Le pipeline redéploie automatiquement la version corrigée sur les deux sites.
3. Une migration n'est **jamais** annulée automatiquement : si le commit fautif contenait une migration, la défaire par une nouvelle migration (ou réimporter le dump exporté avant), puis pousser.

### g) Caches Laravel

Avec le déploiement automatique, **ne plus lancer `optimize` / `route:cache` / `config:cache`** : un cache de routes ou de configuration resterait celui de l'ancienne version. Si cela a déjà été fait, lancer une fois `optimize:clear` (Laravel Toolkit) sur chaque site.
