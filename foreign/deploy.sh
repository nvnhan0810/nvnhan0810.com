#!/usr/bin/env bash
set -Eeuo pipefail

# Run on the deployment host, from any working directory.
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"

trap 'code=$?; printf "[deploy] Failed (exit %s).\n" "$code" >&2; exit "$code"' ERR

for command in git npm; do
  command -v "$command" >/dev/null 2>&1 || {
    printf '[deploy] Missing command: %s\n' "$command" >&2
    exit 1
  }
done

printf '[deploy] Pulling origin main...\n'
git pull origin main

printf '[deploy] Installing foreign dependencies...\n'
npm i --include=dev --no-save

printf '[deploy] Building foreign...\n'
npm run build

printf '[deploy] Complete.\n'
