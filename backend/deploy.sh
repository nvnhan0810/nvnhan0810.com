#!/usr/bin/env bash
set -Eeuo pipefail

# Build as the current user; only Supervisor needs NOPASSWD sudo.
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"

trap 'code=$?; printf "[deploy] Failed (exit %s).\n" "$code" >&2; exit "$code"' ERR

for command in git cargo sudo supervisorctl; do
  command -v "$command" >/dev/null 2>&1 || {
    printf '[deploy] Missing command: %s\n' "$command" >&2
    exit 1
  }
done

printf '[deploy] Pulling origin main...\n'
git pull origin main

printf '[deploy] Building backend release...\n'
cargo build --release --locked --workspace

printf '[deploy] Running database migrations...\n'
cargo run --release --locked --package console --bin migrate -- up

printf '[deploy] Loading master data...\n'
cargo run --release --locked --package console --bin console -- master-data

printf '[deploy] Restarting backend API and queue...\n'
sudo -n supervisorctl restart nvnhan0810-backend nvnhan0810-backend-queue

printf '[deploy] Complete.\n'
