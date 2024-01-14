<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(COVERAGE)', false);

    $parameters->set('coverage.enabled', '%env(bool:COVERAGE)%');

    $parameters->set('coverage.dir', '%bundle.root_dir%/src');

    $parameters->set('coverage.path', '%bundle.root_dir%/cov');
};
