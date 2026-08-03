<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\HealthCheck\BlockingHealthCheck;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->services()
        ->set(BlockingHealthCheck::class)
        ->arg('$seconds', 300)
        ->autoconfigure();
};
