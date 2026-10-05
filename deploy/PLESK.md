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
- Branche : `master` — Mode : **automatique** — Chemin de déploiement : **`staging.daertech.ci`** (la racine du projet, pas `public`).
- Optionnel : copier l'URL de webhook fournie par Plesk dans GitHub > Settings > Webhooks pour déployer à chaque push.

**Actions de déploiement supplémentaires** — coller exactement :

```bash
PHP_BIN=/opt/plesk/php/8.3/bin/php bash deploy/plesk-deploy.sh
```

(Adapter `8.3` à la version choisie à l'étape 2.) Le script installe les dépendances PHP sans paquets de dev, vérifie `.env`/`APP_KEY`, compile les assets si `npm` est disponible, passe le site en maintenance le temps des migrations (remise en ligne garantie même en cas d'erreur), crée `public/storage`, règle les permissions et met en cache config/routes/vues. Il **n'exécute aucun seeder**. On peut le relancer sans risque (bouton « Déployer » de Plesk).

> **Laravel Toolkit** : si l'extension est présente, elle peut afficher le site comme application Laravel et exécuter des commandes Artisan depuis l'interface (utile sans SSH). Ne pas activer en plus son propre déploiement automatique : le script ci-dessus s'en charge déjà.

### Assets front (`public/build`) — important

`public/build` est dans `.gitignore` : **le dépôt Git ne contient pas les fichiers CSS/JS compilés**. Donc :

- **Node.js disponible dans Plesk** (extension Node.js, version 20.19+ ou 22+) : le script lance `npm ci && npm run build` lui-même. Rien à faire.
- **Node.js absent** : le script s'arrête avec un message clair. Il faut alors, à chaque changement du front, compiler en local (`npm ci && npm run build`) puis téléverser le dossier `public/build/` dans `staging.daertech.ci/public/build/` (Gestionnaire de fichiers ou FTP), avant de relancer le déploiement avec `SKIP_NPM=1` devant la commande.

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

Après le premier déploiement, exécuter **une seule fois** (Laravel Toolkit > Artisan, ou tâche planifiée « Exécuter une commande » lancée manuellement puis supprimée) :

```bash
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=OrganisationSeeder --force
php artisan db:seed --class=WorkflowStepSeeder --force
php artisan db:seed --class=ShiftTemplateSeeder --force
php artisan db:seed --class=HoraireSeeder --force
```

Les migrations ne créent que le rôle `secretaire` : **`RoleSeeder` est indispensable** (super_admin, administrateur, coordonnateur_equipe). Ces seeders sont idempotents.

**Ne jamais lancer en production** : `DatabaseSeeder` (crée `admin@example.com` / mot de passe `password` et des shifts de démo), `DevTestDataSeeder`, `ShiftSeeder`, `AssignmentSeeder`, `PieuSeeder` (pieux d'exemple). `PlatformOwnerSeeder` ne fonctionne pas non plus en production (il dépend de Faker, absent avec `--no-dev`). Créer le compte propriétaire ainsi, puis changer le mot de passe à la première connexion :

```bash
php artisan tinker --execute="App\Models\User::forceCreate(['name'=>'Daniel-Eric Aboussou','email'=>'aboussoudaniel@gmail.com','password'=>'MOT_DE_PASSE_TEMPORAIRE','is_platform_owner'=>true,'must_change_password'=>true]);"
```

## 7. Cron et files d'attente

- **Planificateur (cron)** : l'appli ne planifie **aucune** tâche (`routes/console.php` ne contient pas de `Schedule`). **Inutile pour l'instant.** Si on en ajoute un jour : Plesk > **Tâches planifiées**, toutes les minutes :
  `/opt/plesk/php/8.3/bin/php /var/www/vhosts/daertech.ci/staging.daertech.ci/artisan schedule:run`
  (vérifier le chemin réel dans le Gestionnaire de fichiers).
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
