<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\Configuration;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\SwooleExtension;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\CommandGroupRunner;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\LongRunningCommandsWorkerStartHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\TaskWorkerCommands;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\WorkerStopSignal;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * EXPERIMENTAL feature - long running console commands in task workers.
 */
#[CoversClass(Configuration::class)]
#[CoversClass(SwooleExtension::class)]
final class TaskWorkerCommandsConfigurationTest extends TestCase
{
    /**
     * A bare command line is the common case, and normalising it to a one-command group here is what
     * lets everything downstream deal in groups only.
     */
    public function testThatASingleCommandStringBecomesAGroupOfOne(): void
    {
        $config = $this->process(['commands' => ['messenger:consume default']]);

        self::assertSame([['messenger:consume default']], $config['task_worker']['commands']);
    }

    public function testThatAListStaysAGroup(): void
    {
        $config = $this->process([
            'commands' => [
                'messenger:consume default',
                ['messenger:consume a', 'messenger:consume b'],
            ],
        ]);

        self::assertSame(
            [['messenger:consume default'], ['messenger:consume a', 'messenger:consume b']],
            $config['task_worker']['commands'],
        );
    }

    public function testThatCommandsRegisterTheirServices(): void
    {
        $container = $this->load(['commands' => ['messenger:consume default']], coroutinesEnabled: true);

        self::assertTrue($container->hasDefinition(LongRunningCommandsWorkerStartHandler::class));
        self::assertTrue($container->hasDefinition(CommandGroupRunner::class));
        self::assertTrue($container->hasDefinition(WorkerStopSignal::class));
        self::assertSame(
            [['messenger:consume default']],
            $container->getDefinition(TaskWorkerCommands::class)
                ->getArgument('$groups'),
        );
    }

    public function testThatNothingIsRegisteredWithoutConfiguredCommands(): void
    {
        $container = $this->load(['settings' => ['worker_count' => 1]], coroutinesEnabled: true);

        self::assertFalse($container->hasDefinition(LongRunningCommandsWorkerStartHandler::class));
    }

    /**
     * The commands say how many task workers are wanted, so leaving worker_count unset must not be read
     * as "no task workers", which would silently drop every configured command.
     */
    public function testThatWorkerCountDefaultsToTheNumberOfGroups(): void
    {
        $container = $this->load(
            ['commands' => ['first', ['second-a', 'second-b']]],
            coroutinesEnabled: true,
        );

        self::assertSame(2, $this->taskWorkerCount($container));
    }

    public function testThatTooFewTaskWorkersForTheGroupsIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('raise worker_count to at least 2');

        $this->load(
            ['settings' => ['worker_count' => 1], 'commands' => ['first', 'second']],
            coroutinesEnabled: true,
        );
    }

    /**
     * Without a scheduler the one command blocks onWorkerStart and owns the process, so a second command
     * in the same group could never start. Better to refuse than to run only the first one.
     */
    public function testThatSharingATaskWorkerWithoutCoroutinesIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('platform.coroutines.enabled');

        $this->load(['commands' => [['a', 'b']]], coroutinesEnabled: false);
    }

    public function testThatASingleCommandPerWorkerIsAllowedWithoutCoroutines(): void
    {
        $container = $this->load(['commands' => ['a', 'b']], coroutinesEnabled: false);

        self::assertFalse(
            $container->getDefinition(LongRunningCommandsWorkerStartHandler::class)
                ->getArgument('$coroutinesEnabled'),
        );
    }

    private function taskWorkerCount(ContainerBuilder $container): int
    {
        /** @var array<string, mixed> $settings */
        $settings = $container->getDefinition('SwooleBundle\SwooleBundle\Server\HttpServerConfiguration')
            ->getArgument(3);

        return (int) $settings['task_worker_count'];
    }

    /**
     * @param array<string, mixed> $taskWorker
     * @return array<string, mixed>
     */
    private function process(array $taskWorker): array
    {
        return (new Processor())->processConfiguration(
            Configuration::fromTreeBuilder(),
            [['task_worker' => $taskWorker]],
        );
    }

    /**
     * @param array<string, mixed> $taskWorker
     */
    private function load(array $taskWorker, bool $coroutinesEnabled): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.logs_dir', sys_get_temp_dir());
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');

        $config = [
            'task_worker' => $taskWorker,
            'platform' => [
                'coroutines' => [
                    'enabled' => $coroutinesEnabled,
                ],
            ],
        ];

        (new SwooleExtension())->load([$config], $container);

        return $container;
    }
}
