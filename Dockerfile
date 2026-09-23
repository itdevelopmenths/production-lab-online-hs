# ================================
# Stage 1: Build frontend assets
# ================================
FROM node:20-alpine AS node-builder

WORKDIR /var/www

COPY package*.json ./
RUN npm ci

COPY . .
# VITE_APP_NAME di-embed saat build; .env belum ada di tahap ini -> set eksplisit.
ENV VITE_APP_NAME="SC Online"
RUN npm run build

# ================================
# Stage 2: PHP application
# ================================
FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    postgresql-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Copy custom PHP configs
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy application source
COPY --chown=www-data:www-data . /var/www

# Copy Vite build output from node-builder stage
COPY --chown=www-data:www-data --from=node-builder /var/www/public/build /var/www/public/build
# jQuery & DataTables (disalin dari node_modules oleh scripts/copy-vendor.mjs)
COPY --chown=www-data:www-data --from=node-builder /var/www/public/vendor /var/www/public/vendor

# Set permissions
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www

# Change user so composer installs dependencies with correct ownership
USER www-data

# Install PHP dependencies (production only)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Entrypoint: sinkronkan public -> shared volume untuk nginx
USER root
COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/www/public-shared \
    && chown www-data:www-data /var/www/public-shared

USER www-data

EXPOSE 9000
CMD ["/usr/local/bin/entrypoint.sh"]
