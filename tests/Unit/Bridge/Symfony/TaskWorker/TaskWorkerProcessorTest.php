<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    Proxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\TaskWorkerCommands;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\TaskWorkerProcessor;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(TaskWorkerProcessor::class)]
final class TaskWorkerProcessorTest extends TestCase
{
    private const string CONSUME_SERVICE_ID = 'console.command.messenger_consume_messages';

    private const string PROJECTION_SERVICE_ID = 'app.command.projection_run';

    public function testTheConfiguredCommandIsPooled(): void
    {
        $container = $this->newContainer([['messenger:consume default --memory-limit=512M']]);

        $this->process($container);

        self::assertSame(
            [[]],
            $container->getDefinition(self::CONSUME_SERVICE_ID)
                ->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * The case the whole thing exists for: one group, the same command twice, two coroutines that must
     * not end up running one instance between them.
     */
    public function testACommandListedTwiceIsPooledOnce(): void
    {
        $container = $this->newContainer([[
            'messenger:consume default --memory-limit=512M',
            'messenger:consume default_2 --memory-limit=512M',
        ]]);

        $this->process($container);

        self::assertSame(
            [[]],
            $container->getDefinition(self::CONSUME_SERVICE_ID)
                ->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    public function testEveryGroupIsRead(): void
    {
        $container = $this->newContainer([
            ['messenger:consume default'],
            ['app:projection:run'],
        ]);

        $this->process($container);

        foreach ([self::CONSUME_SERVICE_ID, self::PROJECTION_SERVICE_ID] as $serviceId) {
            self::assertTrue(
                $container->getDefinition($serviceId)
                    ->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE),
                sprintf('Expected %s to be pooled.', $serviceId),
            );
        }
    }

    /**
     * Everything else in the application is reached through bin/console, one command per process, where
     * a proxy would be cost with nothing to show for it.
     */
    public function testACommandNoTaskWorkerRunsIsLeftAlone(): void
    {
        $container = $this->newContainer([['messenger:consume default']]);

        $this->process($container);

        self::assertSame(
            [],
            $container->getDefinition(self::PROJECTION_SERVICE_ID)
                ->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * Read through StringInput rather than by taking the first word, so that a line the resolver can run
     * is a line this can pool - the two have to agree on which command a line names.
     */
    public function testACommandLineLeadingWithAnOptionIsStillRecognised(): void
    {
        $container = $this->newContainer([['-vv messenger:consume default']]);

        $this->process($container);

        self::assertTrue(
            $container->getDefinition(self::CONSUME_SERVICE_ID)
                ->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    public function testAnApplicationRunningNoTaskWorkerCommandsIsLeftAlone(): void
    {
        $container = $this->newContainer(null);

        $this->process($container);

        self::assertSame(
            [],
            $container->getDefinition(self::CONSUME_SERVICE_ID)
                ->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * A command line naming something the loader has never heard of is the resolver's to complain
     * about, at the point where it can say which command line it was - not this one's, where the only
     * outcome available is taking the whole compilation down.
     */
    public function testAnUnknownCommandNameIsIgnored(): void
    {
        $container = $this->newContainer([['app:does-not-exist']]);

        $this->expectNotToPerformAssertions();

        $this->process($container);
    }

    private function process(ContainerBuilder $container): void
    {
        (new TaskWorkerProcessor())->process(
            $container,
            new Proxifier($container, new ClassModificationProcessor($container)),
        );
    }

    /**
     * @param array<int, list<string>>|null $groups null for an application that configures no commands,
     *                                              which is when the extension registers no
     *                                              TaskWorkerCommands at all
     */
    private function newContainer(?array $groups): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES, 20);

        $container->register(self::CONSUME_SERVICE_ID);
        $container->register(self::PROJECTION_SERVICE_ID);
        $container->register('console.command_loader', ContainerCommandLoader::class)
            ->setArguments([
                null,
                [
                    'messenger:consume' => self::CONSUME_SERVICE_ID,
                    'app:projection:run' => self::PROJECTION_SERVICE_ID,
                ],
            ]);

        if ($groups !== null) {
            // Positionally, the way the definition looks by the time a before-removing pass sees it.
            $container->register(TaskWorkerCommands::class, TaskWorkerCommands::class)
                ->setArguments([$groups]);
        }

        return $container;
    }
}
