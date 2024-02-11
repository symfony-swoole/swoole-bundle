<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Assert\Assertion;
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
                ->map(static function ($configurator): callable {
                    Assertion::isInstanceOf($configurator, Configurator::class);

                    return $configurator->configure(...);
                })
        );
    }
}
