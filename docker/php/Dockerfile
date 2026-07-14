FROM php:8.3-fpm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpq-dev \
    && docker-php-ext-install \
        intl \
        pdo_pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
