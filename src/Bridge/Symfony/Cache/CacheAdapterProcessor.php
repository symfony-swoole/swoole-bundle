<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Cache;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\SimpleResetter;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Throwable;

final class CacheAdapterProcessor implements CompileProcessor
{
    public function process(ContainerBuilder $container, Proxifier $proxifier): void
    {
        $resetterDefId = 'swoole_bundle.coroutines_support.cache_adapter_resetter';
        $resetterDef = new Definition(SimpleResetter::class);
        $resetterDef->setArguments(['reset']);
        $taggedCount = 0;

        foreach ($container->getDefinitions() as $definition) {
            try {
                /** @var class-string $className */
                $className = $definition->getClass();

                if (!$definition->isAbstract() && is_subclass_of($className, AbstractAdapter::class)) {
                    foreach ($definition->getArguments() as $argument) {
                        if ($argument instanceof AbstractArgument) {
                            continue 2;
                        }
                    }

                    $definition->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, ['resetter' => $resetterDefId]);
                    $taggedCount++;
                }
            } catch (Throwable) {
                // ignore
            }
        }

        if ($taggedCount === 0) {
            return;
        }

        $container->setDefinition($resetterDefId, $resetterDef);
    }
}
