<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\Configuration;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\SwooleExtension;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\CommandGroupRunner;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\Exception\WorkerMaxRequestUnsupported;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\LongRunningCommandsWorkerStartHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\RaiseStopSignalOnTaskWorkerExit;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\RaiseStopSignalOnWorkerShutdown;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\RefuseMaxRequestWithBlockingCommands;
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

    /**
     * With coroutines on, task workers raise the stop flag for themselves, so http workers - which
     * worker_max_request recycles - are kept out of it.
     */
    public function testThatCoroutineModeRaisesFromTaskWorkersOnly(): void
    {
        $container = $this->load(['commands' => ['a']], coroutinesEnabled: true);

        self::assertTrue($container->hasDefinition(RaiseStopSignalOnTaskWorkerExit::class));
        self::assertFalse($container->hasDefinition(RaiseStopSignalOnWorkerShutdown::class));
        self::assertFalse($container->hasDefinition(RefuseMaxRequestWithBlockingCommands::class));
    }

    /**
     * With coroutines off the task worker is blocked in its command and hears nothing, so the http worker
     * has to be the one raising - and worker_max_request has to be watched for at start-up.
     */
    public function testThatBlockingModeRaisesFromAnyWorker(): void
    {
        $container = $this->load(['commands' => ['a']], coroutinesEnabled: false);

        self::assertTrue($container->hasDefinition(RaiseStopSignalOnWorkerShutdown::class));
        self::assertFalse($container->hasDefinition(RaiseStopSignalOnTaskWorkerExit::class));
        self::assertTrue($container->hasDefinition(RefuseMaxRequestWithBlockingCommands::class));
    }

    /**
     * Every recycle of an http worker would stop the blocking commands for good: the only notice they
     * get of the server stopping is an http worker's exit, and a recycled one leaves the same way.
     */
    public function testThatMaxRequestWithBlockingCommandsIsRejected(): void
    {
        $this->expectException(WorkerMaxRequestUnsupported::class);
        $this->expectExceptionMessage('worker_max_request is 100');

        $this->load(['commands' => ['a']], coroutinesEnabled: false, httpServerSettings: [
            'worker_max_request' => 100,
        ]);
    }

    public function testThatMaxRequestIsAllowedWithCoroutines(): void
    {
        $container = $this->load(['commands' => ['a']], coroutinesEnabled: true, httpServerSettings: [
            'worker_max_request' => 100,
        ]);

        self::assertTrue($container->hasDefinition(RaiseStopSignalOnTaskWorkerExit::class));
    }

    public function testThatMaxRequestIsAllowedWithoutCommands(): void
    {
        $container = $this->load(['settings' => ['worker_count' => 1]], coroutinesEnabled: false, httpServerSettings: [
            'worker_max_request' => 100,
        ]);

        self::assertFalse($container->hasDefinition(RefuseMaxRequestWithBlockingCommands::class));
    }

    /**
     * An env placeholder is not a number until the server runs, so the container lets it through and the
     * configurator registered above refuses it then.
     */
    public function testThatAnEnvPlaceholderIsLeftToTheServer(): void
    {
        $container = $this->load(['commands' => ['a']], coroutinesEnabled: false, httpServerSettings: [
            'worker_max_request' => '%env(int:WORKER_MAX_REQUEST)%',
        ]);

        self::assertTrue($container->hasDefinition(RefuseMaxRequestWithBlockingCommands::class));
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
     * @param array<string, mixed> $httpServerSettings
     */
    private function load(array $taskWorker, bool $coroutinesEnabled, array $httpServerSettings = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.logs_dir', sys_get_temp_dir());
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');

        $config = [
            'http_server' => [
                'settings' => $httpServerSettings,
            ],
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
