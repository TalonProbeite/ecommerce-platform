# Build context is services/ (set by compose: context: ..).
# Application sources are taken from order/.

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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY order/composer.json order/composer.lock ./

# COMPOSER_NO_DEV=1 for production images. Default keeps dev tools (phpunit)
# so tests can run inside the same image.
# stripe-php needs ext-curl: it is compiled into the official php image.
ARG COMPOSER_NO_DEV=0
RUN composer install \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    $([ "$COMPOSER_NO_DEV" = "1" ] && echo "--no-dev")

COPY --from=ghcr.io/roadrunner-server/roadrunner:2025.1 /usr/bin/rr ./rr

COPY order/ ./

RUN composer dump-autoload --optimize --no-scripts \
    && mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && touch .rr.yaml \
    && chown -R www-data:www-data \
    storage \
    bootstrap/cache \
    .rr.yaml \
    && chmod -R ug+rwX \
    storage \
    bootstrap/cache \
    && chmod 664 .rr.yaml

ENV OCTANE_STATE_FILE=/app/storage/logs/octane-server-state.json

USER www-data

EXPOSE 8080

# Default command for the HTTP container. Relay, consumer and scheduler override it in compose.
CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8080", "--rpc-port=6001"]
