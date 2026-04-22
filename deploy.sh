#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

if [[ -f ".deploy.env" ]]; then
  # shellcheck disable=SC1091
  source ".deploy.env"
fi

: "${DEPLOY_HOST:?Set DEPLOY_HOST (e.g. 203.0.113.10)}"
: "${DEPLOY_USER:?Set DEPLOY_USER (e.g. deploy)}"
: "${DEPLOY_PATH:?Set DEPLOY_PATH (e.g. /var/www/nvnhan0810.com)}"

DEPLOY_PORT="${DEPLOY_PORT:-22}"
DEPLOY_SSH_KEY="${DEPLOY_SSH_KEY:-}"
DEPLOY_POST_COMMANDS="${DEPLOY_POST_COMMANDS:-}"

ssh_base=(ssh -p "$DEPLOY_PORT" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)
rsync_ssh=(-e "ssh -p $DEPLOY_PORT -o BatchMode=yes -o StrictHostKeyChecking=accept-new")

if [[ -n "$DEPLOY_SSH_KEY" ]]; then
  ssh_base+=( -i "$DEPLOY_SSH_KEY" )
  rsync_ssh=(-e "ssh -i \"$DEPLOY_SSH_KEY\" -p $DEPLOY_PORT -o BatchMode=yes -o StrictHostKeyChecking=accept-new")
fi

SAIL="${SAIL:-./vendor/bin/sail}"

"$SAIL" up -d
"$SAIL" npm ci
"$SAIL" artisan ziggy:generate resources/ts/utils/ziggy --types
mkdir -p resources/ts/types
mv -f resources/ts/utils/ziggy.d.ts resources/ts/types/ziggy.d.ts
mv -f resources/ts/utils/ziggy.js resources/ts/utils/ziggy.ts
"$SAIL" npm run build

rsync_args=(
  -az
  --checksum
  --human-readable
  --partial
  --stats
  --delete 
)

# Sync the app source + built assets. Exclude secrets/runtime/dev-only directories.
rsync_args+=(
  --exclude ".git/"
  --exclude ".github/"
  --exclude ".idea/"
  --exclude ".vscode/"
  --exclude "node_modules/"
  --exclude "vendor/"
  --exclude "storage/"
  --exclude "bootstrap/cache/"
  --exclude ".env"
  --exclude ".env.*"
  --exclude ".deploy.env"
  --exclude "database/database.sqlite"
)

echo "Deploying to ${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}"
rsync "${rsync_args[@]}" "${rsync_ssh[@]}" ./ "${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH%/}/"

"${ssh_base[@]}" "${DEPLOY_USER}@${DEPLOY_HOST}" "cd \"${DEPLOY_PATH%/}\" \
  && mkdir -p bootstrap/cache \
    storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs \
  && chmod -R ug+rwX bootstrap/cache storage"

if [[ -n "$DEPLOY_POST_COMMANDS" ]]; then
  "${ssh_base[@]}" "${DEPLOY_USER}@${DEPLOY_HOST}" "cd \"${DEPLOY_PATH%/}\" && ${DEPLOY_POST_COMMANDS}"
fi

"${ssh_base[@]}" "${DEPLOY_USER}@${DEPLOY_HOST}" "chown -R www-data:www-data \"${DEPLOY_PATH%/}\""

