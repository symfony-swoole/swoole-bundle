<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * EXPERIMENTAL. Pools the console commands configured to run in task workers.
 *
 * A console command is a container service, so it is shared, and a command line resolves to the same
 * instance however many times it is asked for. That is right for `bin/console`, where a process runs
 * one command; it is wrong for a task worker command group, where several coroutines run commands side
 * by side and a group may well list the same command twice. Commands keep per-run state on themselves -
 * `ConsumeMessagesCommand` holds the Worker it is currently running in `$worker`, which is what
 * `handleSignal()` stops - so the second run overwrites the first's, and the stop meant for one consumer
 * lands on the other while the first can no longer be stopped at all.
 *
 * Only the commands actually named in `swoole.task_worker.commands` are pooled. Every other command in
 * the application is left alone: it is reached through `bin/console`, one per process, where a proxy
 * would be pure cost. {@see ApplicationCommandResolver} is the other half of this - a pooled service
 * alone is not enough, because Symfony memoizes the resolved command.
 *
 * The name-to-service-id map comes from `console.command_loader`, which `AddConsoleCommandPass` builds
 * in the before-removing stage at the default priority; `StatefulServicesPass` runs in the same stage at
 * -10000, so the map is always there by the time this runs.
 */
final class TaskWorkerProcessor implements CompileProcessor
{
    private const string COMMAND_LOADER_SERVICE_ID = 'console.command_loader';

    #[Override]
    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        // Registered by the extension only when commands are configured, so its absence is the
        // ordinary case of an application not using the feature.
        if (!$container->hasDefinition(TaskWorkerCommands::class)) {
            return;
        }

        if (!$container->hasDefinition(self::COMMAND_LOADER_SERVICE_ID)) {
            return;
        }

        // By index, not by '$groups'. The extension sets it as a named argument, but named arguments
        // are resolved to positions in the optimization stage, which is over by the time a
        // before-removing pass like this one runs - asking for the name here throws.
        /** @var array<int, list<string>> $groups */
        $groups = $container->getDefinition(TaskWorkerCommands::class)
            ->getArgument(0);
        /** @var array<string, string> $commandMap */
        $commandMap = $container->getDefinition(self::COMMAND_LOADER_SERVICE_ID)
            ->getArgument(1);

        foreach ($this->commandNames($groups) as $commandName) {
            $serviceId = $commandMap[$commandName] ?? null;

            if ($serviceId === null || !$container->hasDefinition($serviceId)) {
                continue;
            }

            // Tagged rather than proxified directly: the tag is what StatefulServicesPass acts on once
            // every compile processor has run, and doing both is refused outright by the Proxifier.
            //
            // No resetter. A command that keeps per-run state clears it on its way out - messenger's
            // nulls $worker in a finally - and the instance is handed back to the pool only when its
            // coroutine ends, which for a task worker command is when the command itself has returned.
            $container->findDefinition($serviceId)
                ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE)
                // Every instance the pool builds arrives with its Application already set, rather than
                // the resolver putting it there afterwards. LazyCommand::getCommand() does that only
                // once - for whichever coroutine reached it first - so with the command pooled the
                // instances after that one would otherwise run with none, and a command line carrying
                // a global option (`-vv`, `--env`) would fail to bind against a definition that never
                // merged the application's.
                //
                // Added here rather than to the proxy: this runs before Proxifier splits the
                // definition, and the split renames this one to <id>.swoole_coop.wrapped with its
                // method calls intact - which is the definition the pool instantiates.
                ->addMethodCall('setApplication', [new Reference(TaskWorkerCommands::APPLICATION_SERVICE_ID)]);
        }
    }

    /**
     * Read the way the resolver reads it, so a line this pools is a line it can also run: through
     * StringInput, which skips leading options rather than assuming the first word is the command.
     *
     * @param array<int, list<string>> $groups
     * @return list<string>
     */
    private function commandNames(array $groups): array
    {
        $names = [];

        foreach ($groups as $group) {
            foreach ($group as $commandLine) {
                $name = (new StringInput($commandLine))->getFirstArgument();

                if ($name === null) {
                    continue;
                }

                $names[$name] = true;
            }
        }

        return array_keys($names);
    }
}
