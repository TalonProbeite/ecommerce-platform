FROM php:8.5-cli-alpine3.24

RUN apk add --no-cache \
        libpq \
        libzip \
        icu-libs \
        oniguruma \
        libxml2 \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        libxml2-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        pcntl \
        pdo_pgsql \
        sockets \
        xml \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf \
        /tmp/* \
        /var/tmp/* \
        /usr/local/lib/php/doc \
        /usr/local/share/doc
