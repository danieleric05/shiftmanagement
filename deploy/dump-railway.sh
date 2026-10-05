#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Exporte la base MySQL de Railway vers un fichier .sql.gz importable dans la
# base MariaDB de Plesk (voir deploy/PLESK.md, « Reprendre les données de Railway »).
#
# À lancer sur VOTRE machine (le mot de passe ne passe jamais par Claude) :
#
#   export MYSQL_PUBLIC_URL='mysql://user:motdepasse@host:port/railway'
#   bash deploy/dump-railway.sh
#
# MYSQL_PUBLIC_URL = variable du service MySQL dans Railway (onglet Variables).
#
# Mode 2 (si « Access denied » ou URL indisponible) : passer par l'endpoint de
# sauvegarde de l'application elle-même, qui utilise ses propres identifiants :
#
#   export BACKUP_URL='https://VOTRE-APP.up.railway.app/system/backup'
#   export BACKUP_TOKEN='valeur de BACKUP_TOKEN dans les variables Railway de l\'app'
#   bash deploy/dump-railway.sh
#
# Le fichier est écrit dans le dossier courant : railway-dump-AAAAMMJJ-HHMM.sql.gz
# (droits 600). Ne le commitez JAMAIS : il contient toutes les données.
#
# Adaptations MySQL 8 -> MariaDB :
#   - collation utf8mb4_0900_ai_ci (inconnue de MariaDB) -> utf8mb4_unicode_ci ;
#   - options de chiffrement de table propres à MySQL 8 retirées.
# ---------------------------------------------------------------------------
set -euo pipefail

if [[ -z "${MYSQL_PUBLIC_URL:-}" && ( -z "${BACKUP_URL:-}" || -z "${BACKUP_TOKEN:-}" ) ]]; then
    echo "[ERREUR] Définir MYSQL_PUBLIC_URL, ou BACKUP_URL + BACKUP_TOKEN (voir l'en-tête du script)." >&2
    exit 1
fi

out="railway-dump-$(date +%Y%m%d-%H%M).sql.gz"
umask 077
# En cas d'échec, ne laisse pas un fichier vide ou partiel.
ok=0
trap '[[ "$ok" == "1" ]] || rm -f "$out"' EXIT

adapter_mariadb() {
    sed -e 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' \
        -e 's/utf8mb3_general_ci/utf8_general_ci/g' \
        -e 's#/\*!80016 DEFAULT ENCRYPTION=[^*]*\*/##g'
}

# --- Mode 2 : endpoint /system/backup de l'application ------------------------
if [[ -z "${MYSQL_PUBLIC_URL:-}" ]]; then
    command -v curl >/dev/null || { echo "[ERREUR] curl requis." >&2; exit 1; }
    echo "==> Export via ${BACKUP_URL} (jeton non affiché)"
    # Le jeton passe par un fichier d'en-têtes temporaire (pas dans la liste des processus).
    hdr="$(mktemp)"; trap '[[ "$ok" == "1" ]] || rm -f "$out"; rm -f "$hdr"' EXIT
    printf 'X-Backup-Token: %s\n' "$BACKUP_TOKEN" > "$hdr"
    curl -fsS --max-time 600 -H @"$hdr" "$BACKUP_URL" | adapter_mariadb | gzip -9 > "$out"
    [[ -s "$out" ]] || { echo "[ERREUR] Export vide." >&2; exit 1; }
    ok=1
    echo "==> Terminé : $out ($(du -h "$out" | cut -f1)). Ne le commitez pas."
    exit 0
fi

# --- Mode 1 : connexion directe à MySQL (Railway) -----------------------------
command -v mysqldump >/dev/null || { echo "[ERREUR] mysqldump introuvable (sudo apt install mysql-client)." >&2; exit 1; }
command -v python3 >/dev/null || { echo "[ERREUR] python3 requis pour lire l'URL." >&2; exit 1; }

# Décodage de l'URL (mot de passe éventuellement encodé) sans l'afficher.
eval "$(python3 - <<'PY'
import os, shlex
from urllib.parse import urlparse, unquote
u = urlparse(os.environ["MYSQL_PUBLIC_URL"])
for k, v in (("DB_HOST", u.hostname or ""), ("DB_PORT", str(u.port or 3306)),
             ("DB_USER", unquote(u.username or "")), ("DB_PASS", unquote(u.password or "")),
             ("DB_NAME", (u.path or "/").lstrip("/"))):
    print(f"{k}={shlex.quote(v)}")
PY
)"
[[ -n "$DB_HOST" && -n "$DB_USER" && -n "$DB_NAME" ]] || { echo "[ERREUR] MYSQL_PUBLIC_URL illisible." >&2; exit 1; }

echo "==> Export de $DB_NAME depuis $DB_HOST:$DB_PORT (mot de passe non affiché)"
# MYSQL_PWD évite d'exposer le mot de passe dans la liste des processus.
MYSQL_PWD="$DB_PASS" mysqldump \
    --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" \
    --single-transaction --quick --no-tablespaces --skip-triggers \
    --default-character-set=utf8mb4 --column-statistics=0 --set-gtid-purged=OFF \
    --skip-comments "$DB_NAME" \
  | adapter_mariadb \
  | gzip -9 > "$out"

[[ -s "$out" ]] || { rm -f "$out"; echo "[ERREUR] Export vide." >&2; exit 1; }
ok=1
echo "==> Terminé : $out ($(du -h "$out" | cut -f1)). Ne le commitez pas."
