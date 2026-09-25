#!/usr/bin/env bash
#
# Prepare an unpacked release and switch the live site to it with no downtime.
#
#   release.sh /var/www/digsignage/releases/1.4.0-20261001120000
#
# Layout (see docs/deployment.md):
#   {base}/releases/*        one folder per release
#   {base}/shared/.env       environment file shared by every release
#   {base}/shared/storage    uploads, logs, sessions shared by every release
#   {base}/current           symlink the web server serves from
#
# Everything up to the switch happens beside the live release, so visitors
# keep being served while dependencies install, assets build and caches warm.
# Migrations run before the switch: keep them backwards compatible (add
# columns/tables first, remove old ones in a later release).
#
# Used by both the Super admin → Updates page and .github/workflows/deploy.yml.

set -Eeuo pipefail

RELEASE_DIR="$(cd "${1:?Usage: release.sh <release-dir>}" && pwd -P)"
BASE_DIR="$(cd "${DEPLOY_BASE_PATH:-$(dirname "$(dirname "$RELEASE_DIR")")}" && pwd -P)"
SHARED_DIR="$BASE_DIR/shared"

# Server-wide deploy settings (DEPLOY_RELOAD_COMMAND, DEPLOY_PHP, ...).
if [ -f "$BASE_DIR/shared/deploy.env" ]; then
    set -a
    # shellcheck disable=SC1091
    . "$BASE_DIR/shared/deploy.env"
    set +a
fi
PHP="${DEPLOY_PHP:-php}"
COMPOSER="${DEPLOY_COMPOSER:-composer}"
NPM="${DEPLOY_NPM:-npm}"
KEEP="${DEPLOY_KEEP_RELEASES:-5}"

step() { printf '\n==> %s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

case "$RELEASE_DIR/" in
    "$BASE_DIR/releases/"*) ;;
    *) fail "The release must be inside $BASE_DIR/releases" ;;
esac

[ -f "$SHARED_DIR/.env" ] || fail "Missing $SHARED_DIR/.env"

# One deploy at a time, whether it came from the Updates page or GitHub Actions.
exec 9>"$BASE_DIR/.deploy.lock"
flock -n 9 || fail "Another deploy or rollback is running on this server"

[ -f "$RELEASE_DIR/artisan" ] || fail "$RELEASE_DIR is not a DigSignage release"

step "Linking shared files"
mkdir -p \
    "$SHARED_DIR/storage/app/public" \
    "$SHARED_DIR/storage/app/private" \
    "$SHARED_DIR/storage/framework/cache/data" \
    "$SHARED_DIR/storage/framework/sessions" \
    "$SHARED_DIR/storage/framework/views" \
    "$SHARED_DIR/storage/logs"
rm -rf "$RELEASE_DIR/storage"
ln -sfn "$SHARED_DIR/storage" "$RELEASE_DIR/storage"
ln -sfn "$SHARED_DIR/.env" "$RELEASE_DIR/.env"
if [ -f "$SHARED_DIR/database/database.sqlite" ]; then
    ln -sfn "$SHARED_DIR/database/database.sqlite" "$RELEASE_DIR/database/database.sqlite"
fi
mkdir -p "$RELEASE_DIR/bootstrap/cache"

cd "$RELEASE_DIR"

if [ ! -f vendor/autoload.php ]; then
    step "Installing PHP dependencies"
    "$COMPOSER" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
fi

if [ ! -f public/build/manifest.json ]; then
    step "Building front-end assets"
    "$NPM" ci --no-audit --no-fund
    "$NPM" run build
    rm -rf node_modules
fi

step "Checking the new release starts"
"$PHP" artisan --version

step "Running database migrations"
"$PHP" artisan migrate --force --no-interaction

step "Caching configuration, routes, views and events"
"$PHP" artisan optimize
"$PHP" artisan storage:link --force >/dev/null 2>&1 || true

step "Switching the live site"
ln -sfn "$RELEASE_DIR" "$BASE_DIR/current.next"
mv -Tf "$BASE_DIR/current.next" "$BASE_DIR/current"
echo "current -> $RELEASE_DIR"

step "Restarting background workers"
"$PHP" artisan queue:restart || true
"$PHP" artisan schedule:interrupt >/dev/null 2>&1 || true
"$PHP" artisan reverb:restart >/dev/null 2>&1 || true
if [ -n "${DEPLOY_RELOAD_COMMAND:-}" ]; then
    bash -c "$DEPLOY_RELOAD_COMMAND" || echo "WARNING: reload command failed; the new release is live but PHP may need a manual reload."
fi

step "Removing old releases (keeping $KEEP)"
find "$BASE_DIR/releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
    | sort -rn \
    | tail -n +"$((KEEP + 1))" \
    | cut -d' ' -f2- \
    | while read -r old; do
        if [ "$old" != "$RELEASE_DIR" ]; then
            echo "removing $old"
            rm -rf -- "$old"
        fi
    done

step "Done"
