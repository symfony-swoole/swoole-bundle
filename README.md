# Swoole Bundle

[![Maintainability](https://qlty.sh/gh/symfony-swoole/projects/swoole-bundle/maintainability.svg)](https://qlty.sh/gh/symfony-swoole/projects/swoole-bundle)
[![Code Coverage](https://qlty.sh/gh/symfony-swoole/projects/swoole-bundle/coverage.svg)](https://qlty.sh/gh/symfony-swoole/projects/swoole-bundle)
[![Open Source Love](https://badges.frapsoft.com/os/v1/open-source.svg?v=103)](https://github.com/ellerbrock/open-source-badges/)
[![MIT Licence](https://badges.frapsoft.com/os/mit/mit.svg?v=103)](https://opensource.org/licenses/mit-license.php)

Symfony integration with [Open Swoole](https://openswoole.com/) and [Swoole](https://wiki.swoole.com/en/#/) to speed up your applications.

| Sponsored by:                         |                                                                                                 |
|---------------------------------------|-------------------------------------------------------------------------------------------------|
| [Blackfire.io](https://blackfire.io/) | [<img src="docs/img/blackfire-io.png" width="100" alt="Blackfire.io"/>](https://blackfire.io/)  |
---

## Build Matrix

| CI Job | Branch [`master`](https://github.com/symfony-swoole/swoole-bundle/tree/master)                                                                                  | Branch [`develop`](https://github.com/symfony-swoole/swoole-bundle/tree/develop)                                                                             |
|--------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Circle | [![CircleCI](https://circleci.com/gh/symfony-swoole/swoole-bundle/tree/master.svg?style=svg)](https://circleci.com/gh/symfony-swoole/swoole-bundle/tree/master) | [![CircleCI](https://circleci.com/gh/symfony-swoole/swoole-bundle/tree/develop.svg?style=svg)](https://circleci.com/gh/symfony-swoole/swoole-bundle/tree/develop) |

## Table of Contents

- [Swoole Bundle](#swoole-bundle)
  - [Build Matrix](#build-matrix)
  - [Table of Contents](#table-of-contents)
  - [Quick start guide](#quick-start-guide)
  - [Features](#features)
  - [Requirements](#requirements)
    - [Current version](#current-version)
    - [Future versions](#future-versions)
    - [Open Swoole](#open-swoole)
      - [Version check](#version-check)
      - [Installation](#installation)

## Quick start guide

1. Make sure you have installed proper Open Swoole PHP Extension and pass other [requirements](#requirements).

2. (optional) Create a new symfony project

    ```bash
    composer create-project symfony/skeleton project

    cd ./project
    ```

3. Install bundle in your Symfony application

    ```bash
    composer require swoole-bundle/swoole-bundle
    ```

   If using OpenSwoole, you need to also install the core package:

    ```bash
    composer require openswoole/core
    ```

4. Edit `config/bundles.php`

    ```php
    return [
        // ...other bundles
        SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\SwooleBundle::class => ['all' => true],
    ];
    ```

5. Run Swoole HTTP Server

    ```bash
    bin/console swoole:server:run
    ```

6. Enter http://localhost:9501

7. You can now configure bundle according to your needs

## Features

-   Built-in API Server

    Swoole Bundle API Server allows managing Swoole HTTP Server in real-time.

    -   Reload worker processes
    -   Shutdown server
    -   Access metrics and settings

-   Improved static files serving

    Swoole HTTP Server provides a default static files handler, but it lacks supporting many `Content-Types`. To overcome this issue, there is a configurable Advanced Static Files Server. Static files serving remains enabled by default in the development environment. Static files directory defaults to `%kernel.project_dir%/public`. To configure your custom mime types check [configuration reference](docs/configuration-reference.md) (key `swoole.http_server.static.mime_types`).

-   Symfony Messenger integration

    _Available since version: `0.6`_

    Swoole Server Task Transport has been integrated into this bundle to allow easy execution of asynchronous actions. Documentation of this feature is available [here](docs/swoole-task-symfony-messenger-transport.md).

-   Long running console commands in task workers **EXPERIMENTAL**

    Runs commands such as `messenger:consume` inside Swoole task worker processes, so one server supervises both the HTTP workers and the consumers. This makes a *worker unit* possible: a single deployable running several worker loops at once while answering HTTP health checks throughout - which a supervisor-per-consumer deployment cannot express. Works with coroutines on (several commands per task worker) and off (one per task worker) - **both modes are experimental**, and shutdown behaviour differs sharply between them. Read the documentation [here](docs/swoole-task-worker-commands.md) before using it.

-   Hot Module Reload (HMR) for development **ALPHA**

    Since Swoole HTTP Server runs in Event Loop and does not flush memory between requests, to keep DX equal with normal servers, this bundle uses code replacement technique, using `inotify` PHP Extension to allow continuous development. It is enabled by default (when the extension is found) and requires no additional configuration. You can turn it off in bundle configuration.

    _Remarks: This feature currently works only on a Linux host machine. It probably won't work with Docker, and it is possible that it works only with configuration: `swoole.http_server.running_mode: process` (default)._

    _A polling-based `stat` mode is also available: it detects changes by polling file modification times from PHP (no `inotify` extension required) and triggers the same graceful worker reload, so it also works in Docker and on macOS. The `auto` default selects `stat` when the `inotify` extension is not loaded (debug builds only)._

    _Note that a worker reload can only apply changes to files that were not already loaded before the workers forked - PHP cannot redeclare a class the forked worker already holds. Applications that load most of their service classes during kernel boot get little or nothing out of it._

    _For reliable local dev, use the [`swoole:server:watch`](docs/docker-usage.md#local-development) console command as a supervisor: it restarts the server on any watched change (with a `php -l` guard), so your edit always takes effect regardless of what was loaded before the fork._

    _Some changes cannot be applied by a worker reload. This happens when the compiled container no longer matches its source files, or when the file was already loaded before the workers were created. In these cases HMR does not reload the workers. It stops and writes one log message that explains what has to be restarted. Documentation of this feature is available [here](docs/hot-module-reload.md)._
  
-   Access logs, (disabled by default) logs are configurable is a same way as apache mod log. Documentation of this feature is available [here](docs/swoole-access-logs.md).

-   Liveness endpoint (disabled by default) on a port of its own, served by a dedicated process so that it keeps answering while every worker is busy. Projects can contribute their own checks to it. Documentation of this feature is available [here](docs/swoole-health.md).

-   Console commands for running, supervising and inspecting the server, plus a `swoole:debug:service-pools` command and a `debug:container` that reports the coroutine service pools. Documentation of every command is available [here](docs/console-commands.md).

## Requirements

### Current version

-   PHP version `>= 8.3 && <= 8.5`
-   Open Swoole PHP Extension `^v26.2.0`
-   Swoole PHP Extension `^6.2.0`+
-   Symfony `^7.4 || ^8.0`
    -   Symfony `^8.0` needs PHP `>= 8.4`, and Symfony `^8.1` needs PHP `>= 8.4.1`

Additional requirements to enable specific features:

-   [Inotify PHP Extension](https://pecl.php.net/package/inotify) `^2.0.0` to use Hot Module Reload (HMR)
    -   When using PHP 8, inotify version `^3.0.0` is required

### Swoole

The bundle requires one of those extensions:
- [Swoole PHP Extension](https://github.com/swoole/swoole-src) version `6.2.0` or higher. Active bug fixes are provided only for the latest version.
- [Open Swoole PHP Extension](https://github.com/openswoole/ext-openswoole) version `22.0.0` or higher. Active bug fixes are provided only for the latest version.

#### Version check

To check your installed version you can run the following command:

```sh
// Swoole
php -r "echo swoole_version() . \PHP_EOL;"

# 6.2.0+

// OpenSwoole
php -r "echo OpenSwoole\Util::getVersion() . \PHP_EOL;"

# 22.0.0+
```

#### Installation

##### Swoole
Official GitHub repository [swoole/swoole-src](https://github.com/swoole/swoole-src#%EF%B8%8F-installation) contains comprehensive installation guide. The recommended approach is to install it [from source](https://github.com/swoole/swoole-src#2-install-from-source-recommended).

##### OpenSwoole
Official GitHub repository [openswoole/ext-openswoole](https://github.com/openswoole/ext-openswoole#installation) contains comprehensive installation guide. The recommended approach is to install it [from source](https://github.com/openswoole/ext-openswoole#2-compile-from-source).