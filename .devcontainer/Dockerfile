FROM mcr.microsoft.com/devcontainers/base:ubuntu-24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        git-lfs \
        unzip \
        zip \
        procps \
        lsof \
        postgresql \
        postgresql-contrib \
        postgresql-client \
        composer \
        php8.3-cli \
        php8.3-common \
        php8.3-bcmath \
        php8.3-curl \
        php8.3-gd \
        php8.3-intl \
        php8.3-mbstring \
        php8.3-pgsql \
        php8.3-sqlite3 \
        php8.3-xml \
        php8.3-zip \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/run/postgresql \
    && chown -R postgres:postgres /var/run/postgresql \
    && git lfs install --system

WORKDIR /workspaces/qa_render
