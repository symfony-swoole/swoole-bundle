<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Monolog;

use Monolog\Handler\RotatingFileHandler as OriginalRotatingFileHandler;
use Monolog\Handler\StreamHandler as OriginalStreamHandler;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Monolog\MonologProcessor;
use SwooleBundle\SwooleBundle\Bridge\Monolog\RotatingFileHandler;
use SwooleBundle\SwooleBundle\Bridge\Monolog\StreamHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    Proxifier,
};
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class MonologProcessorTest extends TestCase
{
    public function testStreamBasedHandlersAreReplacedByTheirCoroutineSafeCounterpart(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('monolog.handler.main', new Definition(OriginalStreamHandler::class));
        $container->setDefinition('monolog.handler.rotating', new Definition(OriginalRotatingFileHandler::class));

        (new MonologProcessor())->process($container, self::newProxifier($container));

        self::assertSame(
            StreamHandler::class,
            $container->getDefinition('monolog.handler.main')->getClass(),
        );
        self::assertSame(
            RotatingFileHandler::class,
            $container->getDefinition('monolog.handler.rotating')->getClass(),
        );
    }

    public function testReplacedHandlersGetTheSwooleAdapterInjected(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('monolog.handler.main', new Definition(OriginalStreamHandler::class));

        (new MonologProcessor())->process($container, self::newProxifier($container));

        $methodCalls = $container->getDefinition('monolog.handler.main')->getMethodCalls();

        self::assertCount(1, $methodCalls);
        self::assertSame('setSwoole', $methodCalls[0][0]);
        self::assertEquals([new Reference(Swoole::class)], $methodCalls[0][1]);
    }

    public function testHandlersWritingToNoSharedStreamAreLeftAlone(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('monolog.handler.test', new Definition(TestHandler::class));

        (new MonologProcessor())->process($container, self::newProxifier($container));

        $definition = $container->getDefinition('monolog.handler.test');

        self::assertSame(TestHandler::class, $definition->getClass());
        self::assertSame([], $definition->getMethodCalls());
    }

    /**
     * Without any monolog.logger alias in the container the proxifier is never asked to do anything.
     */
    private static function newProxifier(ContainerBuilder $container): Proxifier
    {
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());

        return new Proxifier($container, new ClassModificationProcessor($container));
    }
}
