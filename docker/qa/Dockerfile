FROM composer:2 AS composer_bin

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.3-cli-bookworm AS runtime
ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    APP_ENV=qa

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl git unzip zip supervisor postgresql-client \
    libpq-dev libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev \
    libfreetype6-dev libxml2-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
       bcmath gd intl mbstring opcache pdo_pgsql pgsql xml zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer_bin /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/framework/cache/data storage/framework/sessions \
       storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache
COPY --from=assets /app/public/build ./public/build
COPY docker/qa/supervisord.conf /etc/supervisor/conf.d/siget.conf
COPY docker/qa/entrypoint.sh /usr/local/bin/siget-entrypoint
RUN chmod +x /usr/local/bin/siget-entrypoint

EXPOSE 8000
ENTRYPOINT ["siget-entrypoint"]
