<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    /**
     * Coroutines on, which is the whole precondition: MailerProcessor runs from StatefulServicesPass,
     * and that pass returns immediately where coroutines are off.
     *
     * An environment of its own rather than a mailer added to the coroutines one, because every server
     * in that environment would then compile a mail transport factory it has no use for.
     *
     * @see \SwooleBundle\SwooleBundle\Tests\Feature\MailerTransportPoolingTest
     */
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            // Required rather than decorative: with coroutines on, SwooleBundle::boot() asks for
            // swoole_bundle.error_handler.symfony_error_handler, which only the symfony handler
            // registers.
            'exception_handler' => [
                'type' => 'symfony',
            ],
            'settings' => [
                'worker_count' => 1,
            ],
        ],
        'platform' => [
            'coroutines' => [
                'enabled' => true,
                'max_concurrency' => 10,
                'max_service_instances' => 10,
            ],
        ],
    ]);
};
