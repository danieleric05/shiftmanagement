#!/usr/bin/env bash
#
# Construit la branche `staging` = origin/master + public/build compilé, puis la pousse.
#
# Pourquoi : l'hébergement Plesk n'a pas Node.js et déploie une branche Git. public/build
# est ignoré sur master ; on le versionne donc uniquement sur la branche `staging`.
#
# Usage (depuis n'importe où dans le dépôt) : bash deploy/build-staging-branch.sh
#
# - Ne touche pas à la copie de travail : tout se passe dans un `git worktree` temporaire.
# - Idempotent : la branche `staging` est recréée à chaque fois à partir de origin/master.
# - Ne pousse JAMAIS sur master : seule la branche `staging` est poussée.
# - Aucun .env n'est utilisé. VITE_APP_NAME est figé dans le JS à la compilation (pas lu
#   au runtime sur le serveur) : valeur par défaut « Temple Shift Management »,
#   modifiable avec VITE_APP_NAME="Autre nom" bash deploy/build-staging-branch.sh

set -euo pipefail

BRANCH=staging
REMOTE=origin
BASE_REF="$REMOTE/master"

die() { echo "Erreur : $*" >&2; exit 1; }

for cmd in git npm composer; do
    command -v "$cmd" >/dev/null 2>&1 || die "commande « $cmd » introuvable."
done

REPO=$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel)
cd "$REPO"

[ "$BRANCH" != "master" ] || die "la branche cible ne peut pas être master."

if [ -n "$(git status --porcelain)" ]; then
    die "l'arbre de travail n'est pas propre. Commitez ou remisez vos modifications d'abord."
fi

echo "==> Récupération de $REMOTE"
git fetch --prune "$REMOTE"
git rev-parse --verify --quiet "$BASE_REF^{commit}" >/dev/null || die "$BASE_REF introuvable."

MASTER_SHA=$(git rev-parse --short "$BASE_REF")

# Retire les métadonnées de worktrees disparus (exécution précédente interrompue ;
# un éventuel dossier temporaire résiduel dans $TMPDIR peut être supprimé à la main).
git worktree prune

# `staging` est une branche générée : on refuse d'écraser des commits locaux non poussés.
if git rev-parse --verify --quiet "refs/heads/$BRANCH" >/dev/null; then
    if git rev-parse --verify --quiet "refs/remotes/$REMOTE/$BRANCH" >/dev/null; then
        UNPUSHED=$(git rev-list --count "refs/remotes/$REMOTE/$BRANCH..refs/heads/$BRANCH")
    else
        UNPUSHED=$(git rev-list --count "$BASE_REF..refs/heads/$BRANCH")
    fi
    [ "$UNPUSHED" = "0" ] || die "la branche locale $BRANCH a $UNPUSHED commit(s) non poussé(s) ; supprimez-la (git branch -D $BRANCH) si c'est voulu."
fi

# La branche ne doit pas être extraite dans un autre worktree encore présent.
if git worktree list --porcelain | grep -qx "branch refs/heads/$BRANCH"; then
    die "la branche $BRANCH est extraite dans un autre worktree (voir « git worktree list »)."
fi

WORKTREE=$(mktemp -d "${TMPDIR:-/tmp}/shift-staging-build.XXXXXX")

cleanup() {
    cd "$REPO" || return
    git worktree remove --force "$WORKTREE" >/dev/null 2>&1 || true
    rm -rf "$WORKTREE"
    git worktree prune
}
trap cleanup EXIT

echo "==> Worktree temporaire : $WORKTREE (branche $BRANCH depuis $BASE_REF @ $MASTER_SHA)"
git worktree add --force -B "$BRANCH" "$WORKTREE" "$BASE_REF"

cd "$WORKTREE"
[ ! -e .env ] || die ".env inattendu dans le worktree."

# resources/js/app.js importe Ziggy depuis vendor/ : dépendances PHP de prod nécessaires au build.
echo "==> composer install (sans dev, sans scripts)"
composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

export VITE_APP_NAME="${VITE_APP_NAME:-Temple Shift Management}"
echo "==> npm ci && npm run build (VITE_APP_NAME=$VITE_APP_NAME)"
npm ci --no-audit --no-fund
npm run build

[ -f public/build/manifest.json ] || die "public/build/manifest.json absent après le build."

git add -f public/build

# Seul public/build doit être ajouté.
if git diff --cached --name-only | grep -qv '^public/build/'; then
    die "des fichiers hors de public/build sont indexés ; abandon."
fi

git commit --quiet -m "Build front pour staging ($MASTER_SHA)" \
    -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"

echo "==> Push de $BRANCH (force-with-lease)"
git push --force-with-lease="refs/heads/$BRANCH" "$REMOTE" "refs/heads/$BRANCH:refs/heads/$BRANCH"

echo "==> Terminé : $REMOTE/$BRANCH = $(git rev-parse --short HEAD) (master $MASTER_SHA)"
