variable "PHP_VERSION" {
    default = "8.0"
}

variable "COMPOSER_AUTH" {
    default = ""
}

target "cli" {
    cache-from = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-cli"]
    cache-to   = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-cli,mode=max"]
    output     = ["type=registry"]
}

target "composer" {
    cache-from = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-composer"]
    cache-to   = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-composer,mode=max"]
    output     = ["type=registry"]
}

target "ci" {
    cache-from = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-ci"]
    cache-to   = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-ci,mode=max"]
    output     = ["type=registry"]
}

target "coverage-xdebug" {
    cache-from = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-coverage-xdebug"]
    cache-to   = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-coverage-xdebug,mode=max"]
    output     = ["type=registry"]
}

target "coverage-pcov" {
    cache-from = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-coverage-pcov"]
    cache-to   = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-coverage-pcov,mode=max"]
    output     = ["type=registry"]
}

target "merge-code-coverage" {
    cache-from = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-merge-code-coverage"]
    cache-to   = ["type=registry,ref=openswoolebundle/openswoole-bundle-cache:${PHP_VERSION}-merge-code-coverage,mode=max"]
    output     = ["type=registry"]
}
