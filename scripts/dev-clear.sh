#!/usr/bin/env bash
# Clear Laravel caches + restart pod so OPcache picks up PHP changes.
# Usage (from anywhere):
#   ~/www/dev.nvnhan0810.com/scripts/dev-clear.sh
set -euo pipefail

NAMESPACE="${NAMESPACE:-dev-nvnhan0810-com}"
DEPLOYMENT="${DEPLOYMENT:-dev-nvnhan0810-com}"

echo "==> optimize:clear"
kubectl -n "${NAMESPACE}" exec "deploy/${DEPLOYMENT}" -- php artisan optimize:clear

echo "==> rollout restart (flush OPcache)"
kubectl -n "${NAMESPACE}" rollout restart "deployment/${DEPLOYMENT}"
kubectl -n "${NAMESPACE}" rollout status "deployment/${DEPLOYMENT}" --timeout=120s

echo "Done. https://dev.nvnhan0810.com"
