FROM mcr.microsoft.com/devcontainers/php:1-8.3-bookworm

ARG MAILPIT_VERSION=v1.30.6

USER root

ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_PROCESS_TIMEOUT=1200

# Eliminar repositorios externos que puedan romper apt-get update
RUN rm -f /etc/apt/sources.list.d/yarn.list \
          /etc/apt/sources.list.d/yarnpkg.list \
          /etc/apt/sources.list.d/yarn*.list \
    || true

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        jq \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        postgresql-client \
        procps \
        supervisor \
        unzip \
        zip \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN set -eux; \
    architecture="$(dpkg --print-architecture)"; \
    case "${architecture}" in \
        amd64) mailpit_arch="amd64" ;; \
        arm64) mailpit_arch="arm64" ;; \
        *) \
            echo "Arquitectura no soportada para Mailpit: ${architecture}" >&2; \
            exit 1 \
            ;; \
    esac; \
    mailpit_url="https://github.com/axllent/mailpit/releases/download/${MAILPIT_VERSION}/mailpit-linux-${mailpit_arch}.tar.gz"; \
    curl -fL \
        --retry 6 \
        --retry-all-errors \
        --retry-delay 3 \
        "${mailpit_url}" \
        -o /tmp/mailpit.tar.gz; \
    tar -xzf /tmp/mailpit.tar.gz -C /usr/local/bin mailpit; \
    chmod 0755 /usr/local/bin/mailpit; \
    /usr/local/bin/mailpit version; \
    rm -f /tmp/mailpit.tar.gz

COPY .devcontainer/php.ini /usr/local/etc/php/conf.d/99-siget-codespaces.ini

USER vscode
