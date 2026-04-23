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
DEPLOY_ENV_REMOTE_FILE="${DEPLOY_ENV_REMOTE_FILE:-}"
DEPLOY_ENV_USE_SUDO="${DEPLOY_ENV_USE_SUDO:-0}"

ssh_base=(ssh -p "$DEPLOY_PORT" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)
rsync_ssh=(-e "ssh -p $DEPLOY_PORT -o BatchMode=yes -o StrictHostKeyChecking=accept-new")

if [[ -n "$DEPLOY_SSH_KEY" ]]; then
  ssh_base+=( -i "$DEPLOY_SSH_KEY" )
  rsync_ssh=(-e "ssh -i \"$DEPLOY_SSH_KEY\" -p $DEPLOY_PORT -o BatchMode=yes -o StrictHostKeyChecking=accept-new")
fi

SAIL="${SAIL:-./vendor/bin/sail}"

ENV_FILE=".env"
ENV_BACKUP_FILE=".env.bk"
did_backup_env="0"

restore_local_env() {
  if [[ "$did_backup_env" == "1" ]]; then
    rm -f "$ENV_FILE"
    mv -f "$ENV_BACKUP_FILE" "$ENV_FILE"
  fi
}

if [[ -f "$ENV_FILE" ]]; then
  mv -f "$ENV_FILE" "$ENV_BACKUP_FILE"
  did_backup_env="1"
fi

trap restore_local_env EXIT

remote_env_file="${DEPLOY_ENV_REMOTE_FILE:-${DEPLOY_PATH%/}/${ENV_FILE}}"
remote_env_cmd="cat \"${remote_env_file}\""
if [[ "${DEPLOY_ENV_USE_SUDO}" == "1" ]]; then
  remote_env_cmd="sudo ${remote_env_cmd}"
fi

echo "Syncing ${ENV_FILE} from ${DEPLOY_USER}@${DEPLOY_HOST}:${remote_env_file}"
"${ssh_base[@]}" "${DEPLOY_USER}@${DEPLOY_HOST}" "${remote_env_cmd}" > "$ENV_FILE"

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
  --exclude "public/hot"
  --exclude ".env"
  --exclude ".env.*"
  --exclude ".deploy.env"
  --exclude "database/database.sqlite"
)

echo "Deploying to ${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}"
rsync "${rsync_args[@]}" "${rsync_ssh[@]}" ./ "${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH%/}/"

"${ssh_base[@]}" "${DEPLOY_USER}@${DEPLOY_HOST}" "cd \"${DEPLOY_PATH%/}\" \
  && rm -f public/hot \
  && mkdir -p bootstrap/cache \
    storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs \
  && chmod -R ug+rwX bootstrap/cache storage"

if [[ -n "$DEPLOY_POST_COMMANDS" ]]; then
  "${ssh_base[@]}" "${DEPLOY_USER}@${DEPLOY_HOST}" "cd \"${DEPLOY_PATH%/}\" && ${DEPLOY_POST_COMMANDS}"
fi

"${ssh_base[@]}" "${DEPLOY_USER}@${DEPLOY_HOST}" "chown -R www-data:www-data \"${DEPLOY_PATH%/}\""

