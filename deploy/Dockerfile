# syntax=docker/dockerfile:1
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.* ./
COPY resources resources
COPY scripts scripts
COPY public public
RUN npm run build

FROM composer:2 AS dependencies
WORKDIR /app
COPY composer.* ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-progress --ignore-platform-reqs

FROM php:8.3-fpm-bookworm AS app
RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates curl gnupg \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
    && echo 'deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main' > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update && apt-get install -y --no-install-recommends postgresql-client-16 \
    libpq-dev libzip-dev libicu-dev libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libxml2-dev clamdscan \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_pgsql bcmath intl mbstring gd zip opcache pcntl \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=dependencies --chown=www-data:www-data /app/vendor vendor
COPY --from=assets --chown=www-data:www-data /app/public/build public/build
COPY deploy/php.ini /usr/local/etc/php/conf.d/99-siget.ini
COPY deploy/clamd-client.conf /etc/clamav/clamd.conf
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php -r "require 'vendor/autoload.php';" \
    && php artisan package:discover --ansi
USER www-data
ENTRYPOINT ["bash", "deploy/entrypoint.sh"]
CMD ["php-fpm", "-F"]

FROM nginx:stable-alpine AS web
COPY --from=app /var/www/html/public /var/www/html/public
COPY deploy/maintenance.html /var/www/html/public/maintenance.html
COPY deploy/nginx.conf /etc/nginx/conf.d/default.conf

FROM clamav/clamav:1.4 AS antivirus
COPY deploy/clamd-server.conf /etc/clamav/clamd.conf
