#!/usr/bin/env bash
set -Eeuo pipefail

# Run on the deployment host from any working directory.
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
trap 'code=$?; printf "[deploy] Failed (exit %s).\n" "$code" >&2; exit "$code"' ERR

case "${1:-}" in
  '') pull=true ;;
  --build-only) pull=false ;;
  -h|--help)
    printf 'Usage: %s [--build-only]\nBuild tools; by default first fast-forward origin/main.\n' "$0"
    exit 0 ;;
  *) printf '[deploy] Unknown argument: %s\n' "$1" >&2; exit 1 ;;
esac
[[ $# -le 1 ]] || { printf '[deploy] Too many arguments.\n' >&2; exit 1; }

for command in git npm flock; do
  command -v "$command" >/dev/null 2>&1 || {
    printf '[deploy] Missing command: %s\n' "$command" >&2
    exit 1
  }
done

# Share the repository lock with the root deployment script.
state_dir="$(git rev-parse --git-path deploy-state)"
mkdir -p -- "$state_dir"
exec 9>"$state_dir/lock"
flock -n 9 || { printf '[deploy] Another deployment is running.\n' >&2; exit 1; }

if [[ "$pull" == true ]]; then
  [[ "$(git branch --show-current)" == main ]] || {
    printf '[deploy] Check out main before deploying.\n' >&2; exit 1;
  }
  [[ -z "$(git status --porcelain)" ]] || {
    printf '[deploy] Commit or move local changes before deploying.\n' >&2; exit 1;
  }
  printf '[deploy] Pulling origin main...\n'
  git pull --ff-only --no-rebase origin main
fi

printf '[deploy] Installing locked tools dependencies...\n'
npm ci --include=dev
printf '[deploy] Building tools...\n'
npm run build
printf '[deploy] Complete: serve tools/dist with deploy/nginx.conf.\n'
