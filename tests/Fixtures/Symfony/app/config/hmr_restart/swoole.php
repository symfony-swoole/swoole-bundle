<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    /**
     * An HMR server for the one case HMR cannot serve: a change to a file the compiled container was
     * built from.
     *
     * An environment of its own because the test makes this one stale on purpose, by touching the file
     * you are reading. Doing that to a shared environment's config would pause HMR for every server
     * any other test worker happened to have running in it at the time.
     *
     * `stat` rather than `auto` so the test runs the same everywhere - `auto` resolves to inotify
     * where the extension is loaded and to stat where it is not. Which of the two watches the files
     * makes no difference here: the restart conditions wrap both.
     *
     * @see \SwooleBundle\SwooleBundle\Tests\Feature\SwooleServerHMRRestartRequiredTest
     */
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'static' => 'auto',
            'hmr' => [
                'enabled' => 'stat',
            ],
            'settings' => [
                // One worker, so the warning is logged once. Every worker runs an HMR timer and every
                // one of them reports the pause for itself - correctly, since each is a process that
                // has to be replaced - and the test counts the lines.
                'worker_count' => 1,
            ],
        ],
    ]);
};
