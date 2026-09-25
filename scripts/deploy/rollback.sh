#!/usr/bin/env bash
#
# Point the live site back at an earlier release that is still on disk.
#
#   rollback.sh [/var/www/digsignage/releases/1.3.0-20260920090000]
#
# Without an argument, switches to the newest release other than the live one.
# Database migrations are not reversed; releases are expected to keep working
# with the schema of the release after them (see release.sh).

set -Eeuo pipefail

BASE_DIR="$(cd "${DEPLOY_BASE_PATH:-$(dirname "$(dirname "$(cd "$(dirname "$0")/../.." && pwd -P)")")}" && pwd -P)"
# Server-wide deploy settings (DEPLOY_RELOAD_COMMAND, DEPLOY_PHP, ...).
if [ -f "$BASE_DIR/shared/deploy.env" ]; then
    set -a
    # shellcheck disable=SC1091
    . "$BASE_DIR/shared/deploy.env"
    set +a
fi
PHP="${DEPLOY_PHP:-php}"

step() { printf '\n==> %s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

# One deploy at a time, whether it came from the Updates page or GitHub Actions.
exec 9>"$BASE_DIR/.deploy.lock"
flock -n 9 || fail "Another deploy or rollback is running on this server"

CURRENT="$(readlink -f "$BASE_DIR/current" || true)"

if [ -n "${1:-}" ]; then
    TARGET="$(cd "$1" && pwd -P)"
else
    TARGET="$(find "$BASE_DIR/releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
        | sort -rn | cut -d' ' -f2- | grep -vxF "$CURRENT" | head -n 1 || true)"
    [ -n "$TARGET" ] || fail "There is no earlier release to roll back to"
fi

case "$TARGET/" in
    "$BASE_DIR/releases/"*) ;;
    *) fail "The release must be inside $BASE_DIR/releases" ;;
esac

[ "$TARGET" != "$CURRENT" ] || fail "$TARGET is already live"
[ -f "$TARGET/vendor/autoload.php" ] || fail "$TARGET is incomplete"

step "Refreshing caches for $TARGET"
"$PHP" "$TARGET/artisan" optimize

step "Switching the live site"
ln -sfn "$TARGET" "$BASE_DIR/current.next"
mv -Tf "$BASE_DIR/current.next" "$BASE_DIR/current"
echo "current -> $TARGET"

step "Restarting background workers"
"$PHP" "$TARGET/artisan" queue:restart || true
"$PHP" "$TARGET/artisan" schedule:interrupt >/dev/null 2>&1 || true
"$PHP" "$TARGET/artisan" reverb:restart >/dev/null 2>&1 || true
if [ -n "${DEPLOY_RELOAD_COMMAND:-}" ]; then
    bash -c "$DEPLOY_RELOAD_COMMAND" || echo "WARNING: reload command failed; PHP may need a manual reload."
fi

step "Done"
