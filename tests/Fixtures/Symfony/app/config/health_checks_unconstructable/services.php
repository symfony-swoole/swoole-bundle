<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\HealthCheck\UnconstructableHealthCheck;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->services()
        ->set(UnconstructableHealthCheck::class)
        ->autoconfigure();
};
