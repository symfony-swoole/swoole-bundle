<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\HealthCheck\FlaggedHealthCheck;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->services()
        ->set(FlaggedHealthCheck::class)
        ->arg('$flagFile', '%kernel.project_dir%/var/health-check-unhealthy')
        ->autoconfigure();
};
