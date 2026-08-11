<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    /**
     * The bare minimum for SwooleBundle::boot() to reach its error handler registration: coroutines on,
     * and the Symfony exception handler so swoole_bundle.error_handler.symfony_error_handler exists.
     * No server is ever started in this environment - KernelBootWithoutErrorHandlerTest only boots a
     * kernel.
     *
     * It exists as an environment of its own rather than reusing "coroutines" because the cache
     * directory is per environment, and boot-kernel.php must not share a compiled container with any
     * other test. Its container carries an include_once of boot-kernel.php itself, which is harmless in
     * the process that wrote it and fatal in any other: the script would run a second time as a side
     * effect of loading the container, fail its own precondition and exit(2), taking the unrelated
     * process down with it.
     */
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'exception_handler' => [
                'type' => 'symfony',
            ],
        ],
        'platform' => [
            'coroutines' => [
                'enabled' => true,
            ],
        ],
    ]);
};
