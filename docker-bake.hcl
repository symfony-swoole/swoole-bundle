variable "PHP_VERSION" {
    default = "8.1"
}

variable "SWOOLE" {
    default = "openswoole-v22.1.2"
}

variable "COMPOSER_AUTH" {
    default = ""
}

target "cli" {
    cache-from = ["type=registry,ref=symfonywithswoole/swoole-bundle-cache:${PHP_VERSION}-{$SWOOLE}-cli"]
    cache-to   = ["type=registry,ref=symfonywithswoole/swoole-bundle-cache:${PHP_VERSION}-{$SWOOLE}-cli,mode=max"]
    output     = ["type=registry"]
}

target "composer" {
    cache-from = ["type=registry,ref=symfonywithswoole/swoole-bundle-cache:${PHP_VERSION}-${SWOOLE}-composer"]
    cache-to   = ["type=registry,ref=symfonywithswoole/swoole-bundle-cache:${PHP_VERSION}-${SWOOLE}-composer,mode=max"]
    output     = ["type=registry"]
}

target "ci" {
    cache-from = ["type=registry,ref=symfonywithswoole/swoole-bundle-cache:${PHP_VERSION}-${SWOOLE}-ci"]
    cache-to   = ["type=registry,ref=symfonywithswoole/swoole-bundle-cache:${PHP_VERSION}-${SWOOLE}-ci,mode=max"]
    output     = ["type=registry"]
}

target "coverage-xdebug" {
    cache-from = ["type=registry,ref=symfonywithswoole/swoole-bundle-cache:${PHP_VERSION}-${SWOOLE}-coverage-xdebug"]
    cache-to   = ["type=registry,ref=symfonywithswoole/swoole-bundle-cache:${PHP_VERSION}-${SWOOLE}-coverage-xdebug,mode=max"]
    output     = ["type=registry"]
}

target "coverage-pcov" {
    cache-from = ["type=registry,ref=symfonywithswoole/swoole-bundle-cache:${PHP_VERSION}-${SWOOLE}-coverage-pcov"]
    cache-to   = ["type=registry,ref=symfonywithswoole/swoole-bundle-cache:${PHP_VERSION}-${SWOOLE}-coverage-pcov,mode=max"]
    output     = ["type=registry"]
}

target "merge-code-coverage" {
    cache-from = ["type=registry,ref=symfonywithswoole/swoole-bundle-cache:${PHP_VERSION}-merge-code-coverage"]
    cache-to   = ["type=registry,ref=symfonywithswoole/swoole-bundle-cache:${PHP_VERSION}-merge-code-coverage,mode=max"]
    output     = ["type=registry"]
}
