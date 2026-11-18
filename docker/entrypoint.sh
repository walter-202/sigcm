#!/usr/bin/env sh
set -e

PORT="${PORT:-10000}"

# Reemplazar el puerto dinámico en la configuración de Nginx
sed "s/__PORT__/${PORT}/g" /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

# Asegurar estructura y permisos de storage y cache
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

echo "==> Publicando recursos de Filament y optimizando configuración..."
php artisan filament:assets --ansi || true
php artisan storage:link --force --ansi || true
php artisan config:clear --ansi

echo "==> Ejecutando migraciones y esquemas SQL de SIGCM (PostgreSQL)..."
php artisan migrate --force --ansi

echo "==> Ejecutando seeders de roles y usuarios de demostración..."
php artisan db:seed --force --ansi

php artisan config:cache --ansi
php artisan route:cache --ansi
php artisan view:cache --ansi

echo "==> Iniciando PHP-FPM y Nginx en el puerto ${PORT}..."
php-fpm -D
exec nginx -g "daemon off;"
