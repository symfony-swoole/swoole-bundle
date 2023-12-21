# Swoole Bundle

[![Maintainability](https://api.codeclimate.com/v1/badges/1d73a214622bba769171/maintainability)](https://codeclimate.com/github/symfony-swoole/swoole-bundle/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/1d73a214622bba769171/test_coverage)](https://codeclimate.com/github/symfony-swoole/swoole-bundle/test_coverage)
[![Open Source Love](https://badges.frapsoft.com/os/v1/open-source.svg?v=103)](https://github.com/ellerbrock/open-source-badges/)
[![MIT Licence](https://badges.frapsoft.com/os/mit/mit.svg?v=103)](https://opensource.org/licenses/mit-license.php)

Symfony integration with [Open Swoole](https://openswoole.com/) to speed up your applications.

| Sponsored by:                         |                                                                                                 |
|---------------------------------------|-------------------------------------------------------------------------------------------------|
| [Blackfire.io](https://blackfire.io/) | [<img src="docs/img/blackfire-io.png" width="100" alt="Blackfire.io"/>](https://blackfire.io/)  |
| [Travis CI](https://travis-ci.com/)   | [<img src="https://www.travis-ci.com/wp-content/uploads/2022/09/Logo.png" width="100" alt="Travis CI"/>](https://travis-ci.com/)       |
---

## Build Matrix

| CI Job  | Branch [`master`](https://github.com/symfony-swoole/swoole-bundle/tree/master)                                                                                   | Branch [`develop`](https://github.com/symfony-swoole/swoole-bundle/tree/develop)                                                                             |
| ------- |-------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Circle  | [![CircleCI](https://circleci.com/gh/symfony-swoole/swoole-bundle/tree/master.svg?style=svg)](https://circleci.com/gh/symfony-swoole/swoole-bundle/tree/master) | [![CircleCI](https://circleci.com/gh/symfony-swoole/swoole-bundle/tree/develop.svg?style=svg)](https://circleci.com/gh/symfony-swoole/swoole-bundle/tree/develop) |
| CodeCov | [![codecov](https://codecov.io/gh/symfony-swoole/swoole-bundle/branch/master/graph/badge.svg)](https://codecov.io/gh/symfony-swoole/swoole-bundle)                    | [![codecov](https://codecov.io/gh/symfony-swoole/swoole-bundle/branch/develop/graph/badge.svg)](https://codecov.io/gh/symfony-swoole/swoole-bundle)               |
| Travis  | [![Build Status](https://app.travis-ci.com/symfony-swoole/swoole-bundle.svg?branch=master)](https://travis-ci.com/symfony-swoole/swoole-bundle)                       | [![Build Status](https://app.travis-ci.com/symfony-swoole/swoole-bundle.svg?branch=develop)](https://travis-ci.com/symfony-swoole/swoole-bundle)                  |

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
        K911\Swoole\Bridge\Symfony\Bundle\SwooleBundle::class => ['all' => true],
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

-   Hot Module Reload (HMR) for development **ALPHA**

    Since Swoole HTTP Server runs in Event Loop and does not flush memory between requests, to keep DX equal with normal servers, this bundle uses code replacement technique, using `inotify` PHP Extension to allow continuous development. It is enabled by default (when the extension is found) and requires no additional configuration. You can turn it off in bundle configuration.

    _Remarks: This feature currently works only on a Linux host machine. It probably won't work with Docker, and it is possible that it works only with configuration: `swoole.http_server.running_mode: process` (default)._
  
-   Access logs, (disabled by default) logs are configurable is a same way as apache mod log. Documentation of this feature is available [here](docs/swoole-access-logs.md).

## Requirements

### Current version

-   PHP version `>= 8.1 && <= 8.3`
-   Open Swoole PHP Extension `^v22.1.2`
-   Swoole PHP Extension `^5.1.1`
-   Symfony `^5.4.22 || ^6.3`

### Future versions

-   PHP version `> 8.3`
-   Open Swoole PHP Extension `>= v23.0.0`
-   Symfony `^6.4 || ^7.0`

Additional requirements to enable specific features:

-   [Inotify PHP Extension](https://pecl.php.net/package/inotify) `^2.0.0` to use Hot Module Reload (HMR)
    -   When using PHP 8, inotify version `^3.0.0` is required

### Swoole

The bundle requires one of those extensions:
- [Swoole PHP Extension](https://github.com/swoole/swoole-src) version `5.1.1` or higher. Active bug fixes are provided only for the latest version.
- [Open Swoole PHP Extension](https://github.com/openswoole/ext-openswoole) version `22.0.0` or higher. Active bug fixes are provided only for the latest version.

#### Version check

To check your installed version you can run the following command:

```sh
// Swoole
php -r "echo swoole_version() . \PHP_EOL;"

# 5.1.1

// OpenSwoole
php -r "echo OpenSwoole\Util::getVersion() . \PHP_EOL;"

# 22.0.0
```

#### Installation

##### Swoole
Official GitHub repository [swoole/swoole-src](https://github.com/swoole/swoole-src#%EF%B8%8F-installation) contains comprehensive installation guide. The recommended approach is to install it [from source](https://github.com/swoole/swoole-src#2-install-from-source-recommended).

##### OpenSwoole
Official GitHub repository [openswoole/ext-openswoole](https://github.com/openswoole/ext-openswoole#installation) contains comprehensive installation guide. The recommended approach is to install it [from source](https://github.com/openswoole/ext-openswoole#2-compile-from-source).