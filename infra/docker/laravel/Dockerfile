FROM composer:2.8 AS vendor

WORKDIR /app

COPY laravel/composer.json laravel/composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

COPY laravel ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi

FROM composer:2.8 AS vendor-dev

WORKDIR /app

COPY laravel/composer.json laravel/composer.lock ./
RUN composer install \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

COPY laravel ./
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

FROM node:22-alpine AS assets

WORKDIR /app

COPY laravel/package.json laravel/package-lock.json ./
RUN npm ci

COPY laravel/resources ./resources
COPY laravel/public ./public
COPY laravel/vite.config.js ./
RUN npm run build

FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache \
        icu-libs \
        libcurl \
        libpq \
        libzip \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        curl-dev \
        icu-dev \
        libzip-dev \
        linux-headers \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

COPY infra/docker/laravel/php.ini /usr/local/etc/php/conf.d/waflow.ini
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build
COPY --chown=www-data:www-data docs /var/www/docs

RUN mkdir -p \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm", "-F"]

FROM app AS test

USER root
COPY --from=vendor-dev --chown=www-data:www-data /app /var/www/html
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data

CMD ["php", "artisan", "test", "--configuration=phpunit.postgres.xml"]

FROM nginx:1.27-alpine AS web

COPY infra/docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor /app/public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build

EXPOSE 80
