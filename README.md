# ShiftManagement

Application de gestion des servants (bénévoles) et des shifts d'un temple : rosters, rotations, gouvernance, recrutement, entretiens, gestion multi-organisation avec licences.

Stack : [Laravel 13](https://laravel.com) (PHP 8.3+) · [Inertia.js](https://inertiajs.com) + [Vue 3](https://vuejs.org) · MySQL 8 · [Tailwind CSS](https://tailwindcss.com).

## Fonctionnalités principales

- **Servants** : fiche complète, parcours d'intégration (workflow d'étapes), photo, historique d'affectations.
- **Shifts** : modèles de shift réutilisables, postes, affectations de servants, membres de gouvernance (chef d'équipe, coordinateur…).
- **Relèves & permutations** : demandes de remplacement ponctuel ou de changement définitif de shift, avec autorisation restreinte au shift géré par le demandeur.
- **Recrutement** : suivi des besoins par shift, pipeline candidats → entretiens → conversion en servant.
- **Gouvernance** : avis et demandes de retrait sur un servant.
- **Notifications** en base (cloche de l'interface) pour les événements clés (nouvelle demande, résolution, rappel d'entretien).
- **Journal d'activité** : traçabilité des créations/modifications/suppressions (`spatie/laravel-activitylog`), avec suppressions logiques (`SoftDeletes`) sur les entités principales.
- **Multi-organisation** avec gestion de licence par organisation, rôle `platform-owner` pour l'administration globale.

## Installation locale

Prérequis : PHP 8.3+, Composer, Node 22+, MySQL 8.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
# Renseigner DB_DATABASE / DB_USERNAME / DB_PASSWORD dans .env

php artisan migrate --seed
npm run build   # ou `npm run dev` pour le hot-reload en développement
php artisan serve
```

## Tests

```bash
php artisan test
```

## Qualité de code

```bash
vendor/bin/pint --test          # style de code (Laravel Pint)
vendor/bin/phpstan analyse       # analyse statique (Larastan, niveau 5)
```

`phpstan-baseline.neon` fige les erreurs préexistantes au moment de l'introduction de l'outil (essentiellement des propriétés Eloquent non typées dans des closures génériques) — seules les nouvelles erreurs font échouer la CI. Pour régénérer la baseline après un nettoyage : `vendor/bin/phpstan analyse --generate-baseline`.

**Dette technique connue** : pas de lint JavaScript/Vue configuré (ESLint) — à mettre en place lors d'une prochaine passe, en prévoyant le temps de corriger le style sur l'ensemble des fichiers `.vue` existants.

## CI

Le pipeline GitHub Actions (`.github/workflows/tests.yml`) exécute à chaque push/PR sur `master` : build frontend, Pint, PHPStan, puis la suite de tests contre une base MySQL éphémère.

## Tâches planifiées (déploiement)

Le rappel d'entretien programmé (`app:envoyer-rappels-entretiens`, quotidien à 8h) nécessite le scheduler Laravel actif en production :

```
* * * * * cd /chemin/vers/le/projet && php artisan schedule:run >> /dev/null 2>&1
```

`QUEUE_CONNECTION=database` est configuré mais aucun job n'est actuellement dispatché — pas de worker `queue:work` nécessaire pour l'instant.

## Stockage des fichiers

Les photos de servants sont stockées sur le disque privé `local` (`storage/app/private`) et ne sont jamais exposées par URL publique directe : elles sont servies via une route authentifiée (`GET /servants/{servant}/photo`) qui vérifie l'organisation de l'utilisateur.
