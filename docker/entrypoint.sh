#!/bin/sh
set -eu
cd /var/www/html
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${1:-web}" = "web" ]; then
    exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/app.conf
fi

exec runuser -u www-data -- "$@"
