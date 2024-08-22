# Docker usage

## Streaming logs

Symfony application logs are kept in files by default. In Docker containers, which should be ephemeral, you should either stream logs to specific service (like AWS CloudWatch) or print them to the `stdout` or `sterr` streams. For both cases you should use `monolog` bundle.

```bash
composer require monolog
```

### Streaming Symfony application logs to `stdout` in Docker

Example configuration using `monolog` and `symfony`, can be found in demo project.

Relevant configuration files:

- [docker-compose.yml](https://github.com/k911/swoole-bundle-symfony-demo/blob/master/docker-compose.yml)
- [config/services.yaml](https://github.com/k911/swoole-bundle-symfony-demo/blob/master/config/services.yaml)
- [config/packages/dev/monolog.yaml](https://github.com/k911/swoole-bundle-symfony-demo/blob/master/config/packages/dev/monolog.yaml)
- [config/packages/prod/monolog.yaml](https://github.com/k911/swoole-bundle-symfony-demo/blob/master/config/packages/prod/monolog.yaml)

### Streaming Swoole HTTP Server logs to `stdout` in Docker

To get Swoole HTTP Server stream internal logs to `stdout`, use this configuration:

```yaml
# source: https://github.com/k911/swoole-bundle-symfony-demo/blob/master/config/packages/swoole.yaml

parameters:
    ...
    env(SWOOLE_LOG_STREAM_PATH): "%kernel.logs_dir%/swoole_%kernel.environment%.log"

swoole:
    http_server:
        ...
        settings:
            log_file: "%env(resolve:SWOOLE_LOG_STREAM_PATH)%"
```

In your `docker-compose.yml` file set environment variable `SWOOLE_LOG_STREAM_PATH` to `/proc/self/fd/1` value, which is real `stdout` in docker container.

```yaml
# source: https://github.com/k911/swoole-bundle-symfony-demo/blob/master/docker-compose.yml

version: "3.6"
services:
    app:
        ...
        environment:
            SWOOLE_LOG_STREAM_PATH: /proc/self/fd/1
```

This way you'll have logs locally in `var/logs/swoole_*.log` file and printed to `stdout` while running in docker.

### Debug log configuration

To enable debug log processor when Symfony is run from CLI set env variable `APP_RUNTIME_MODE: 'web=1'`, or parameter `kernel.runtime_mode.web: true` (Since Symfony ^6.4). 
Without debug log processor, there are no logs being shown in the symfony profiler.

## Recommended Dockerfile

Features:

- Multi-stage builds (fast rebuilds)
- Secure (run as custom user)
- Portable (add as many PHP extensions as you want)

```dockerfile
ARG PHP_TAG="7.3-cli-alpine3.9"

FROM php:$PHP_TAG as ext-builder
RUN docker-php-source extract && \
    apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS

FROM ext-builder as ext-swoole
ARG SWOOLE_VERSION="4.3.3"
RUN pecl install swoole-${SWOOLE_VERSION} && \
    docker-php-ext-enable swoole

FROM composer:latest as app-installer
WORKDIR /usr/src/app
RUN composer global require "hirak/prestissimo:^0.3" --prefer-dist --no-progress --no-suggest --classmap-authoritative --ansi
COPY composer.json composer.lock symfony.lock ./
RUN composer validate
ARG COMPOSER_ARGS="install"
RUN composer ${COMPOSER_ARGS} --prefer-dist --ignore-platform-reqs --no-progress --no-suggest --no-scripts --no-autoloader --ansi
COPY . ./
RUN composer dump-autoload --classmap-authoritative --ansi

FROM php:$PHP_TAG as base
WORKDIR /usr/src/app
RUN addgroup -g 1000 -S runner && \
    adduser -u 1000 -S app -G runner && \
    chown app:runner /usr/src/app
RUN apk add --no-cache libstdc++
# php -i | grep 'PHP API' | sed -e 's/PHP API => //'
ARG PHP_API_VERSION="20180731"
COPY --from=ext-swoole /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/swoole.so /usr/local/lib/php/extensions/no-debug-non-zts-${PHP_API_VERSION}/swoole.so
COPY --from=ext-swoole /usr/local/etc/php/conf.d/docker-php-ext-swoole.ini /usr/local/etc/php/conf.d/docker-php-ext-swoole.ini

FROM base as App
USER app:runner
COPY --chown=app:runner --from=app-installer /usr/src/app ./
ENTRYPOINT ["./bin/console"]
CMD ["swoole:server:run"]
```

## Demo project

If you want to quickly test above configuration on your computer, clone [`k911/swoole-bundle-symfony-demo`](https://github.com/k911/swoole-bundle-symfony-demo) repository and run two simple commands:

```bash
git clone https://github.com/k911/swoole-bundle-symfony-demo.git
cd swoole-bundle-symfony-demo

docker-compose build
docker-compose up
```

## Local development

For easy local realtime app development use entry point file bellow. For non test/development env script will run server with standard command `bin/console swoole:server:run`.
After swoole server start, script will watch for file changes and perform soft or hard swoole server reload.

Requirements:

- In swoole configuration set `hmr: enabled: ` to `external`.
- You can configure `file_path`, don forget to reflect this path in shell script bellow.
- For `inotifywait` command you need to install lib `inotify-tools` (standard lib available in official repositories).
- In some cases, big/more projects inotify watch limit can be reached, then you need just increase `max_user_watches`.

Configuration:

- Edit: `appDir`, `cacheDir` base on your app configuration
- `inotifywait` line with folders to watch for changes can be adjusted too

```shell
#!/bin/bash

# not compatible with: set -e
set -o pipefail

if [[ $ENV != "test" && $ENV != "development" ]]; then

    # production, staging, feature, ...
    exec bin/console swoole:server:run;

else

  envCache='test';
  if [[ $ENV == "development" ]]; then
    envCache='dev';
  fi

  # edit paths to your app needs
  appDir="/var/www";
  appCacheDir="${appDir}/var/cache";
  cacheDir="${appCacheDir}/${envCache}/swoole_bundle";
  # pid file must be outside env cache to prevent deletion (cache:clear, ...)
  pidFile="${appCacheDir}/swoole_${ENV}.pid";

  if [ -z "$(which inotifywait)" ]; then
      echo "inotifywait not installed. Running swoole without reload.";
      echo "In most distros, it is available in the inotify-tools package.";
      exec bin/console swoole:server:run;
  fi

  # start server in detached mode
  # --pid-file is used to distinct pid file for local test/dev environment
  bin/console swoole:server:start --open-console --pid-file="$pidFile";

  # files included before server start cannot be reloaded - server hard restart
  IFS=$'\n' read -d '' -r -a nonReloadableFiles < ${cacheDir}/nonReloadableFiles.txt;
  IFS=$'\n' read -d '' -r -a nonReloadableAppFiles < ${cacheDir}/nonReloadableAppFiles.txt;
  #echo "${nonReloadableFiles[@]}"

  # https://linux.die.net/man/1/inotifywait
  # -r recursively
  # -q quiet
  # -e events
  # -m monitor, execute indefinitely

  lastModificationTime=0;

  # %w%f - full path with file name
  # %T - timestamp
  inotifywait -rmq -e modify -e delete ${appDir}/config ${appDir}/src ${appDir}/vendor/composer ${appDir}/tests --format %w%f:%T --timefmt %s | while IFS=':' read -r changedFile changedTime; do

    # skip if file contains ~ on end (can be changed below in array comparison somehow)
    if [[ $changedFile == *~ ]]; then
        continue ;
    fi

    # if modification time is different, there are more events/files in same sec, we want only first
    if [[ "$changedTime" =~ ^[0-9]+$ && ${lastModificationTime} -lt "$changedTime" ]]; then

      lastModificationTime=$changedTime;

      if [[ "${nonReloadableFiles[*]}" =~ $changedFile ]]; then

        echo "Changed file:timestamp:  $changedFile:$changedTime";

        # check if application files are valid by php lint
        if time printf "%s\0" "${nonReloadableAppFiles[@]}" | xargs -0 -n 1 -P $(nproc) php -l &> /dev/null; then
          if ! time bin/console swoole:server:stop --no-delay --pid-file="$pidFile"; then
              # in some cases (invalid app settings) stop command is failing
              continue ;
          fi

          # wait until port is available again, close connection coroutine has 3 sec timeout (can be configured)
          while timeout 1 bash -c "(echo > /dev/tcp/0.0.0.0/80) &>/dev/null"; do sleep 0.05; done

          bin/console swoole:server:start --open-console --pid-file="$pidFile";
          # refresh arrays of files
          IFS=$'\n' read -d '' -r -a nonReloadableFiles < ${cacheDir}/nonReloadableFiles.txt;
          IFS=$'\n' read -d '' -r -a nonReloadableAppFiles < ${cacheDir}/nonReloadableAppFiles.txt;

        else
          echo "Status code: ${PIPESTATUS[0]}, skip hard reload server, php files are not valid";
        fi

      else

        bin/console swoole:server:reload --pid-file="$pidFile";

      fi
    fi
  done

fi

```

