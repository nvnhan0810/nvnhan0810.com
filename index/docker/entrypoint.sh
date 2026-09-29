#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  storage/app/og-cache \
  bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# Redis is required for QUEUE_CONNECTION=redis / CACHE_STORE=redis (survives pod rebuilds).
if ! php -m 2>/dev/null | grep -qi '^redis$'; then
  echo "ERROR: PHP redis extension missing — rebuild the image." >&2
  exit 1
fi

if [ "$APP_ENV" = "production" ]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

# K8s: use `args` (Docker CMD) to run one-shot / worker processes while keeping
# this ENTRYPOINT (storage + config cache). Example:
#   args: ["php", "artisan", "queue:work", "redis", ...]
# Bare web Deployment (no args) → supervisord (php-fpm + nginx + inertia-ssr).
if [ "$#" -gt 0 ]; then
  exec "$@"
fi

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
