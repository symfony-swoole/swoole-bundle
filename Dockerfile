ARG PHP_TAG="7.4-cli-alpine3.16"
ARG COMPOSER_TAG="2.3.10"

FROM php:$PHP_TAG as ext-builder
RUN apk add --no-cache linux-headers
RUN docker-php-source extract && \
    apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS

FROM ext-builder as ext-inotify
RUN pecl install inotify && \
    docker-php-ext-enable inotify

FROM ext-builder as ext-pcntl
RUN docker-php-ext-install pcntl

FROM ext-builder as ext-intl
RUN apk add --no-cache icu-dev && \
    docker-php-ext-install intl

FROM ext-builder as ext-apcu
RUN pecl install apcu && \
    docker-php-ext-enable apcu && \
    echo "apc.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini && \
    echo "apc.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini

FROM ext-builder as ext-mysql
RUN docker-php-ext-install pdo_mysql && \
    docker-php-ext-install pdo_mysql

FROM ext-builder as ext-ffi
RUN apk add --no-cache libffi-dev unzip \
    && docker-php-ext-configure ffi --with-ffi \
    && docker-php-ext-install ffi

FROM ext-builder as ext-xdebug
ARG XDEBUG_TAG="3.1.6"
RUN pecl install "xdebug-$XDEBUG_TAG" && \
    docker-php-ext-enable xdebug

FROM ext-builder as ext-openswoole
RUN apk add --no-cache git
ARG SWOOLE_VERSION="4.10.0"
RUN if $(echo "$SWOOLE_VERSION" | grep -qE '^[4-9]\.[0-9]+\.[0-9]+$'); then SWOOLE_GIT_REF="v$SWOOLE_VERSION"; else SWOOLE_GIT_REF="$SWOOLE_VERSION"; fi && \
    git clone https://github.com/openswoole/swoole-src.git --branch "$SWOOLE_GIT_REF" --depth 1 && \
    cd swoole-src && \
    phpize && \
    ./configure && \
    make && \
    make install && \
    docker-php-ext-enable openswoole

FROM ext-builder as ext-pcov
RUN pecl install pcov && \
    docker-php-ext-enable pcov
RUN echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini && \
    echo "pcov.directory=/usr/src/app/src" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

FROM php:$PHP_TAG as base
WORKDIR /usr/src/app
RUN addgroup -g 1000 -S runner && \
    adduser -u 1000 -S app -G runner && \
    chown app:runner /usr/src/app
RUN apk add --no-cache libstdc++ icu lsof libffi vim
# php -i | grep 'PHP API' | sed -e 's/PHP API => //'
ARG PHP_API_VERSION="20190902"
COPY --from=ext-openswoole /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/openswoole.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/openswoole.so
COPY --from=ext-openswoole /usr/local/etc/php/conf.d/docker-php-ext-openswoole.ini /usr/local/etc/php/conf.d/docker-php-ext-openswoole.ini
COPY --from=ext-inotify /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/inotify.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/inotify.so
COPY --from=ext-inotify /usr/local/etc/php/conf.d/docker-php-ext-inotify.ini /usr/local/etc/php/conf.d/docker-php-ext-inotify.ini
COPY --from=ext-pcntl /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/pcntl.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/pcntl.so
COPY --from=ext-pcntl /usr/local/etc/php/conf.d/docker-php-ext-pcntl.ini /usr/local/etc/php/conf.d/docker-php-ext-pcntl.ini
COPY --from=ext-intl /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/intl.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/intl.so
COPY --from=ext-intl /usr/local/etc/php/conf.d/docker-php-ext-intl.ini /usr/local/etc/php/conf.d/docker-php-ext-intl.ini
COPY --from=ext-apcu /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/apcu.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/apcu.so
COPY --from=ext-apcu /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini
COPY --from=ext-mysql /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/pdo_mysql.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/pdo_mysql.so
COPY --from=ext-mysql /usr/local/etc/php/conf.d/docker-php-ext-pdo_mysql.ini /usr/local/etc/php/conf.d/docker-php-ext-pdo_mysql.ini
COPY --from=ext-ffi /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/ffi.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/ffi.so
COPY --from=ext-ffi /usr/local/etc/php/conf.d/docker-php-ext-ffi.ini /usr/local/etc/php/conf.d/docker-php-ext-ffi.ini

FROM composer:${COMPOSER_TAG} AS composer-bin
FROM base as app-installer
ENV COMPOSER_ALLOW_SUPERUSER="1"
COPY --chown=app:runner --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer validate
ARG COMPOSER_ARGS="install"
ARG COMPOSER_AUTH
RUN composer ${COMPOSER_ARGS} --prefer-dist --no-progress --no-autoloader --ansi
COPY . ./
RUN composer dump-autoload --classmap-authoritative --ansi

FROM base as base-coverage-xdebug
RUN apk add --no-cache bash
ARG PHP_API_VERSION="20190902"
COPY --from=ext-xdebug /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/xdebug.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/xdebug.so
COPY --from=ext-xdebug /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
USER app:runner
ENV COVERAGE="1" \
    COMPOSER_ALLOW_SUPERUSER="1" \
    XDEBUG_MODE="coverage"
COPY --chown=app:runner --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY --chown=app:runner --from=app-installer /usr/src/app ./

FROM base as base-coverage-pcov
ARG PHP_API_VERSION="20190902"
COPY --from=ext-pcov /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/pcov.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/pcov.so
COPY --from=ext-pcov /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
USER app:runner
ENV COVERAGE="1" \
    COMPOSER_ALLOW_SUPERUSER="1"
COPY --chown=app:runner --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY --chown=app:runner --from=app-installer /usr/src/app ./

FROM base as base-pcov-xdebug
ARG PHP_API_VERSION="20190902"
COPY --from=ext-pcov /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/pcov.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/pcov.so
COPY --from=ext-pcov /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
COPY --from=ext-xdebug /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/xdebug.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/xdebug.so
COPY --from=ext-xdebug /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
USER app:runner
ENV COVERAGE="1" \
    COMPOSER_ALLOW_SUPERUSER="1" \
    XDEBUG_MODE="coverage"
COPY --chown=app:runner --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY --chown=app:runner --from=app-installer /usr/src/app ./

FROM base as cli
USER app:runner
COPY --chown=app:runner --from=app-installer /usr/src/app ./
ENTRYPOINT ["./tests/Fixtures/Symfony/app/console"]
CMD ["swoole:server:run"]

FROM cli as Composer
ENV COMPOSER_ALLOW_SUPERUSER="1"
COPY --chown=app:runner --from=composer-bin /usr/bin/composer /usr/local/bin/composer
ENTRYPOINT ["composer"]
CMD ["test"]

FROM base-coverage-xdebug as CoverageXdebug
ENTRYPOINT ["composer"]
CMD ["unit-code-coverage"]

FROM base-coverage-pcov as CoveragePcov
ENTRYPOINT ["composer"]
CMD ["unit-code-coverage"]

FROM base-pcov-xdebug as MergeCodeCoverage
ENTRYPOINT ["composer"]
CMD ["merge-code-coverage"]

FROM base-coverage-xdebug as CoverageXdebugWithRetry
ENTRYPOINT ["/bin/bash"]
CMD ["tests/run-feature-tests-code-coverage.sh"]
