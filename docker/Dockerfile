# PHP with Composer: the harness installs this package and every omnistate/*
# source at start. intl for international domain names (omnistate/iana).
FROM php:8.4-cli-alpine
RUN apk add --no-cache git unzip icu-dev libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" intl zip \
    && apk del $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_CACHE_DIR=/harness/.cache
WORKDIR /harness
ENTRYPOINT ["/omnistate/core/docker/entrypoint.sh"]
CMD ["bare"]
