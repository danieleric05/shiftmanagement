#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Déploiement ShiftManagement sur Plesk (extension Git, « actions de
# déploiement supplémentaires »). Voir deploy/PLESK.md.
#
# Usage (depuis la racine du projet) :
#   bash deploy/plesk-deploy.sh
#
# Variables facultatives :
#   PHP_BIN       binaire PHP >= 8.3 (défaut : détection /opt/plesk/php/8.x)
#   COMPOSER_BIN  commande composer (défaut : détection)
#   NPM_BIN       binaire npm (défaut : détection, sinon on saute le build)
#   SKIP_NPM=1    ne pas compiler les assets (public/build doit exister)
#
# Idempotent : peut être relancé sans risque. N'exécute AUCUN seeder.
# ---------------------------------------------------------------------------
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

log()  { printf '\n==> %s\n' "$*"; }
fail() { printf '\n[ERREUR] %s\n' "$*" >&2; exit 1; }

# --- PHP ------------------------------------------------------------------
if [[ -z "${PHP_BIN:-}" ]]; then
    for candidate in /opt/plesk/php/8.5/bin/php /opt/plesk/php/8.4/bin/php /opt/plesk/php/8.3/bin/php; do
        if [[ -x "$candidate" ]]; then PHP_BIN="$candidate"; break; fi
    done
    PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
fi
[[ -n "$PHP_BIN" && -x "$PHP_BIN" ]] || fail "PHP introuvable. Définir PHP_BIN=/opt/plesk/php/8.3/bin/php."
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' \
    || fail "PHP >= 8.3 requis ($PHP_BIN est en $("$PHP_BIN" -r 'echo PHP_VERSION;'))."
log "PHP : $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

artisan() { "$PHP_BIN" artisan "$@"; }

# --- .env / APP_KEY (avant composer : ses scripts démarrent l'application) --
[[ -f .env ]] || fail ".env absent dans $APP_DIR. Copier .env.staging.example en .env et le compléter (voir deploy/PLESK.md)."
app_key="$(grep -E '^APP_KEY=' .env | tail -n1 | cut -d= -f2- | tr -d '"'"'"' \r')"
[[ -n "$app_key" ]] || fail "APP_KEY vide dans .env. Générer une clé : php artisan key:generate --show, puis la coller dans .env."
if grep -qE '^DB_(DATABASE|USERNAME|PASSWORD)=À_REMPLIR' .env; then
    fail "Des variables DB_* valent encore « À_REMPLIR » dans .env."
fi

# --- Dépendances PHP --------------------------------------------------------
if [[ -n "${COMPOSER_BIN:-}" ]]; then
    read -r -a composer_cmd <<< "$COMPOSER_BIN"
elif command -v composer >/dev/null 2>&1; then
    # Le wrapper `composer` utilise le premier `php` du PATH : on force le bon.
    export PATH="$(dirname "$PHP_BIN"):$PATH"
    composer_cmd=(composer)
elif [[ -f /usr/lib/plesk-9.0/composer.phar ]]; then
    composer_cmd=("$PHP_BIN" /usr/lib/plesk-9.0/composer.phar)
elif [[ -f "$APP_DIR/composer.phar" ]]; then
    composer_cmd=("$PHP_BIN" "$APP_DIR/composer.phar")
else
    fail "Composer introuvable. Définir COMPOSER_BIN, ou déposer composer.phar à la racine du projet."
fi
# Caches d'un déploiement précédent : supprimés avant que les scripts
# composer (package:discover) ne démarrent l'application.
rm -f bootstrap/cache/config.php bootstrap/cache/routes-*.php bootstrap/cache/events.php

log "composer install (--no-dev)"
"${composer_cmd[@]}" install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist

# Vues compilées périmées. (Pas d'optimize:clear : il viderait le cache
# « database », dont la table n'existe pas encore au premier déploiement.)
artisan view:clear

# --- Assets front (Vite) -----------------------------------------------------
# public/hot ferait pointer l'appli vers un serveur Vite de dev : jamais en prod.
rm -f public/hot

if [[ -z "${NPM_BIN:-}" && "${SKIP_NPM:-0}" != "1" ]]; then
    NPM_BIN="$(command -v npm || true)"
    if [[ -z "$NPM_BIN" ]]; then
        for candidate in /opt/plesk/node/24/bin/npm /opt/plesk/node/22/bin/npm /opt/plesk/node/20/bin/npm; do
            if [[ -x "$candidate" ]]; then NPM_BIN="$candidate"; break; fi
        done
    fi
fi

if [[ "${SKIP_NPM:-0}" != "1" && -n "${NPM_BIN:-}" ]]; then
    export PATH="$(dirname "$NPM_BIN"):$PATH"
    log "Build des assets avec $NPM_BIN (node $(node -v 2>/dev/null || echo '?'))"
    "$NPM_BIN" ci --no-audit --no-fund
    "$NPM_BIN" run build
else
    log "npm indisponible (ou SKIP_NPM=1) : pas de build, vérification de public/build"
    if [[ ! -f public/build/manifest.json ]]; then
        fail "public/build/manifest.json absent. public/build est ignoré par Git : compiler en local (npm ci && npm run build) puis téléverser le dossier public/build via le Gestionnaire de fichiers/FTP, ou activer Node.js dans Plesk. Voir deploy/PLESK.md."
    fi
    echo "public/build présent (assets téléversés manuellement : pensez à les mettre à jour si le front a changé)."
fi

# --- Dossiers et permissions -------------------------------------------------
log "Permissions storage/ et bootstrap/cache/"
mkdir -p storage/app/public storage/app/private storage/framework/{cache/data,sessions,views,testing} storage/logs bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache

# --- Migrations sous maintenance --------------------------------------------
# Si le site était déjà en maintenance avant le script, on le laisse ainsi.
put_down=0
bring_up() {
    if [[ "$put_down" == "1" ]]; then
        artisan up || echo "[ATTENTION] 'php artisan up' a échoué : relancer manuellement." >&2
        put_down=0
    fi
}
trap bring_up EXIT

if [[ -f storage/framework/down ]]; then
    log "Site déjà en maintenance : il le restera après le script."
else
    log "Mode maintenance"
    artisan down --retry=15
    put_down=1
fi

log "Migrations"
artisan migrate --force

bring_up

# --- Lien public/storage (sans échec s'il existe déjà) ------------------------
if [[ -e public/storage || -L public/storage ]]; then
    echo "public/storage existe déjà."
else
    artisan storage:link || echo "[ATTENTION] storage:link a échoué (symlinks interdits ?). Non bloquant : l'appli sert les photos via PHP."
fi

# --- Caches de production -----------------------------------------------------
log "Caches (config, routes, vues)"
artisan config:cache
artisan route:cache
artisan view:cache

log "Déploiement terminé."
