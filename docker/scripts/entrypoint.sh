#!/bin/sh
set -e

# Sinkronkan folder public (termasuk hasil build Vite yang sudah di-bake dalam
# image) ke shared volume, agar nginx menyajikan asset versi yang SAMA dengan
# aplikasi. Tanpa ini, nginx menyajikan public host yang basi -> 404 asset.
if [ -d /var/www/public-shared ]; then
    cp -R /var/www/public/. /var/www/public-shared/ 2>/dev/null || true
fi

exec php-fpm
