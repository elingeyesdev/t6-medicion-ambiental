#!/bin/sh
set -eu

PORT="${PORT:-8080}"
case "$PORT" in
    ''|*[!0-9]*) echo "PORT debe ser un número." >&2; exit 1 ;;
esac
if [ "${#PORT}" -gt 5 ] || [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
    echo "PORT debe estar entre 1 y 65535." >&2
    exit 1
fi
if [ -z "${APP_KEY:-}" ]; then
    echo "Configura una APP_KEY persistente antes de iniciar GeoSur." >&2
    exit 1
fi

# El volumen puede estar vacío al montarlo por primera vez.
mkdir -p storage/app/private storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache
sed -ri "s/^Listen [0-9]+$/Listen $PORT/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \\*:[0-9]+>/<VirtualHost *:$PORT>/" \
    /etc/apache2/sites-available/000-default.conf
php artisan config:clear
exec apache2-foreground
