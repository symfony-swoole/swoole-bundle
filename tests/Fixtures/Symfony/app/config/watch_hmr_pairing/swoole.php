<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    /**
     * A server with everything a developer actually runs turned on at once: HMR watching inside the
     * workers, and swoole:server:watch supervising from outside.
     *
     * watch_cache_clear turns HMR off to keep one actor in the frame, which makes it precise about the
     * supervisor and silent about the pairing. This is the pairing, and it is the configuration that
     * ships - the documented answer to a change a reload cannot apply is "run it under
     * swoole:server:watch", which is only true if the two of them get along.
     *
     * `stat` rather than `auto`, for the reason hmr_restart gives: `auto` is inotify where the
     * extension is loaded and stat where it is not, and inotify does not report changes reliably
     * through a Docker bind mount. Which of them watches makes no difference to what is asserted here.
     *
     * The test edits the file you are reading, so it needs an environment of its own - a shared one
     * would restart every other worker's server at the same time.
     *
     * @see \SwooleBundle\SwooleBundle\Tests\Feature\SwooleServerWatchWithHmrTest
     */
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'static' => 'auto',
            'hmr' => [
                'enabled' => 'stat',
            ],
            'settings' => [
                'worker_count' => 1,
            ],
        ],
    ]);
};
