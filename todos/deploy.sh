#!/usr/bin/env bash
set -Eeuo pipefail

# Run on the deployment host, from any working directory.
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"

trap 'code=$?; printf "[deploy] Failed (exit %s).\n" "$code" >&2; exit "$code"' ERR

# SSH, cron and Supervisor shells may skip the user's profile, so load user tools here.
for dir in "$HOME/.local/bin" "${FNM_DIR:-$HOME/.local/share/fnm}"; do
  if [[ -d "$dir" && ":$PATH:" != *":$dir:"* ]]; then PATH="$dir:$PATH"; fi
done
export PATH
# fnm puts its default Node version on PATH.
if command -v fnm >/dev/null 2>&1; then
  eval "$(fnm env --shell bash)"
fi

for command in git node npm; do
  command -v "$command" >/dev/null 2>&1 || {
    printf '[deploy] Missing command: %s\n' "$command" >&2
    exit 1
  }
done

printf '[deploy] Pulling origin main...\n'
git pull origin main

printf '[deploy] Installing todos dependencies...\n'
npm i --include=dev --no-save

printf '[deploy] Building todos...\n'
npm run build

printf '[deploy] Complete.\n'
