FROM php:8.5-cli-alpine3.24 AS php-base

RUN apk add --no-cache \
        libpq \
        libzip \
        icu-libs \
        oniguruma \
        libxml2 \
        curl \
        ca-certificates


FROM php-base AS php-extensions

RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        libxml2-dev \
    \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        sockets \
        xml \
        zip \
    \
    && pecl install redis \
    && docker-php-ext-enable redis \
    \
    && apk del .build-deps \
    && rm -rf \
        /tmp/* \
        /var/tmp/* \
        /usr/local/lib/php/doc \
        /usr/local/share/doc

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"


FROM php-extensions AS dependencies

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader



FROM dependencies AS build

COPY . .

RUN composer dump-autoload \
        --classmap-authoritative \
        --no-dev

RUN ./vendor/bin/rr get-binary \
    && chmod +x ./rr

RUN rm -rf \
        /root/.composer \
        /tmp/* \
        /var/tmp/*


FROM php-extensions AS production

WORKDIR /app

RUN addgroup -g 1000 -S app \
    && adduser -u 1000 -S -D -G app -s /sbin/nologin app

COPY --from=build --chown=app:app /app /app

RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R app:app \
        storage \
        bootstrap/cache \
    && chmod -R ug+rwX \
        storage \
        bootstrap/cache

USER app

EXPOSE 8000

CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8000"]