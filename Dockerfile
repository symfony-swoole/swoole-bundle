ARG PHP_TAG="8.0-cli-alpine3.16"
ARG COMPOSER_TAG="2.5.2"

FROM php:$PHP_TAG AS ext-builder
RUN apk add --no-cache linux-headers
RUN docker-php-source extract && \
    apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS

FROM ext-builder AS ext-inotify
RUN pecl install inotify && \
    docker-php-ext-enable inotify

FROM ext-builder AS ext-pcntl
RUN docker-php-ext-install pcntl

FROM ext-builder AS ext-intl
RUN apk add --no-cache icu-dev && \
    docker-php-ext-install intl

FROM ext-builder AS ext-apcu
RUN pecl install apcu && \
    docker-php-ext-enable apcu && \
    echo "apc.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini && \
    echo "apc.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini

FROM ext-builder AS ext-mysql
RUN docker-php-ext-install pdo_mysql && \
    docker-php-ext-install pdo_mysql

FROM ext-builder AS ext-ffi
RUN apk add --no-cache libffi-dev unzip \
    && docker-php-ext-configure ffi --with-ffi \
    && docker-php-ext-install ffi

FROM ext-builder AS ext-xdebug
ARG XDEBUG_TAG="3.2.0"
RUN pecl install "xdebug-$XDEBUG_TAG" && \
    docker-php-ext-enable xdebug

FROM ext-builder AS ext-swoole
RUN apk add --no-cache git
ARG SWOOLE_EXTENSION="openswoole"
ARG SWOOLE_VERSION="4.12.1"
RUN if $(echo "${SWOOLE_VERSION}" | grep -qE '^[4-9]\.[0-9]+\.[0-9]+$'); then SWOOLE_GIT_REF="v${SWOOLE_VERSION}"; else SWOOLE_GIT_REF="${SWOOLE_VERSION}"; fi && \
    if [ "$SWOOLE_EXTENSION" == "openswoole" ]; then SWOOLE_GIT_REPOSITORY="openswoole/ext-openswoole"; else SWOOLE_GIT_REPOSITORY="swoole/swoole-src"; fi && \
    git clone "https://github.com/${SWOOLE_GIT_REPOSITORY}.git" --branch "${SWOOLE_GIT_REF}" --depth 1 src && \
    cd src && \
    phpize && \
    ./configure && \
    make && \
    make install && \
    docker-php-ext-enable "${SWOOLE_EXTENSION}"

FROM ext-builder AS ext-pcov
RUN pecl install pcov && \
    docker-php-ext-enable pcov
RUN echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini && \
    echo "pcov.directory=/usr/src/app/src" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

FROM php:$PHP_TAG AS base
WORKDIR /usr/src/app
RUN addgroup -g 1000 -S runner && \
    adduser -u 1000 -S app -G runner && \
    chown app:runner /usr/src/app
RUN apk add --no-cache libstdc++ icu lsof libffi vim
# php -i | grep 'PHP API' | sed -e 's/PHP API => //'
ARG PHP_API_VERSION="20200930"
ARG SWOOLE_EXTENSION="openswoole"
COPY --from=ext-swoole /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/${SWOOLE_EXTENSION}.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/${SWOOLE_EXTENSION}.so
COPY --from=ext-swoole /usr/local/etc/php/conf.d/docker-php-ext-${SWOOLE_EXTENSION}.ini /usr/local/etc/php/conf.d/docker-php-ext-${SWOOLE_EXTENSION}.ini
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
FROM base AS composer
ENV COMPOSER_ALLOW_SUPERUSER="1"
COPY --chown=app:runner --from=composer-bin /usr/bin/composer /usr/local/bin/composer

FROM composer AS ci
RUN apk add --no-cache git gpg gpg-agent gpgv

FROM composer AS base-coverage-xdebug
RUN apk add --no-cache bash
ARG PHP_API_VERSION="20200930"
COPY --from=ext-xdebug /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/xdebug.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/xdebug.so
COPY --from=ext-xdebug /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
USER app:runner
ENV COVERAGE="1" \
    COMPOSER_ALLOW_SUPERUSER="1" \
    XDEBUG_MODE="coverage"

FROM composer AS base-coverage-pcov
ARG PHP_API_VERSION="20200930"
COPY --from=ext-pcov /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/pcov.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/pcov.so
COPY --from=ext-pcov /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
USER app:runner
ENV COVERAGE="1" \
    COMPOSER_ALLOW_SUPERUSER="1"

FROM ci AS base-pcov-xdebug
ARG PHP_API_VERSION="20200930"
COPY --from=ext-pcov /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/pcov.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/pcov.so
COPY --from=ext-pcov /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
COPY --from=ext-xdebug /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/xdebug.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/xdebug.so
COPY --from=ext-xdebug /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
USER app:runner
ENV COVERAGE="1" \
    COMPOSER_ALLOW_SUPERUSER="1" \
    XDEBUG_MODE="coverage"

FROM composer AS cli
USER app:runner
COPY --chown=app:runner --from=composer /usr/src/app ./
ENTRYPOINT ["./tests/Fixtures/Symfony/app/console"]
CMD ["swoole:server:run"]

FROM base-coverage-xdebug AS CoverageXdebug
ENTRYPOINT ["composer"]
CMD ["unit-code-coverage"]

FROM base-coverage-pcov AS CoveragePcov
ENTRYPOINT ["composer"]
CMD ["unit-code-coverage"]

FROM base-pcov-xdebug AS MergeCodeCoverage
ENTRYPOINT ["composer"]
CMD ["merge-code-coverage"]

FROM base-coverage-xdebug AS CoverageXdebugWithRetry
ENTRYPOINT ["/bin/bash"]
CMD ["tests/run-feature-tests-code-coverage.sh"]
