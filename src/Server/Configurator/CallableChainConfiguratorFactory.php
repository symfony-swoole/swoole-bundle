<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use SwooleBundle\SwooleBundle\Component\GeneratedCollection;

final class CallableChainConfiguratorFactory
{
    /**
     * @param iterable<Configurator> $configuratorCollection
     */
    public function make(iterable $configuratorCollection, Configurator ...$configurators): CallableChainConfigurator
    {
        return new CallableChainConfigurator(
            (new GeneratedCollection($configuratorCollection, ...$configurators))
                ->map(static fn(Configurator $configurator): callable => $configurator->configure(...)),
        );
    }
}
