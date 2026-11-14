#!/bin/sh
set -e
cd /var/www/html

# Volume ilk kez bağlandığında eksik klasörleri oluştur
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache

# Sadece web container'ı (CONTAINER_ROLE=app) migration / storage link yapar
if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        php artisan migrate --force || echo ">>> UYARI: migration başarısız oldu, loglara bak (sunucu yine de başlatılıyor)" >&2
    fi
    php artisan storage:link --force >/dev/null 2>&1 || true
fi

if [ "${LARAVEL_CACHE:-true}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache || true
    php artisan view:cache
else
    php artisan optimize:clear >/dev/null 2>&1 || true
fi

# Artisan root olarak çalıştığı için oluşan dosyaları www-data'ya ver
chown -R www-data:www-data storage bootstrap/cache

# Apache kendi içinde www-data'ya düşer; worker/scheduler'ı doğrudan www-data ile çalıştır
if [ "${CONTAINER_ROLE:-app}" != "app" ]; then
    exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"
fi
exec "$@"
