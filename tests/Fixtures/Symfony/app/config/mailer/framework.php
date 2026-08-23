<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\MailerTransportReportCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    /**
     * A mailer with an SMTP dsn, which is what registers the transport factories this environment is
     * here to look at. Nothing is ever sent through it and no connection is ever made - see the report
     * command for why building a transport does not touch the network.
     */
    $containerConfigurator->extension('framework', [
        'mailer' => [
            'dsn' => 'smtp://localhost:1',
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // The smtp factory by name: the transports themselves are objects inside a final Transports rather
    // than services, so the factory is the only end of this that can be asked anything.
    $services->set(MailerTransportReportCommand::class)
        ->arg('$factory', service('mailer.transport_factory.smtp'));
};
