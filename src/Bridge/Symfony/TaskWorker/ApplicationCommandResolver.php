<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\Exception\CommandNotRunnable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

/**
 * EXPERIMENTAL. Resolves command lines against the application's own console Application.
 *
 * The Application is built once per worker process, on first use rather than in the constructor: the
 * resolver itself is constructed in the master, while resolve() is only ever called after the fork, so
 * building it lazily keeps every command service - and anything it opens - on the worker's side of the
 * fork instead of being inherited from the master.
 *
 * Note this never calls Application::run(). find() registers the commands by itself, so going straight
 * to the resolved command skips doRunCommand() and with it the console signal registration that would
 * be dead weight inside a swoole worker.
 */
final class ApplicationCommandResolver implements CommandResolver
{
    private ?Application $application = null;

    public function __construct(private readonly KernelInterface $kernel) {}

    #[Override]
    public function resolve(string $commandLine): ResolvedCommand
    {
        $input = new StringInput($commandLine);
        // Nothing is attached to stdin in a worker; a command stopping to ask a question would hang
        // the task worker with no way to answer it.
        $input->setInteractive(false);

        $name = $input->getFirstArgument();

        if ($name === null || trim($commandLine) === '') {
            throw CommandNotRunnable::empty();
        }

        try {
            $command = $this->unwrap(
                $this->application()
                    ->find($name)
            );
        } catch (Throwable $throwable) {
            throw CommandNotRunnable::notResolvable($commandLine, $throwable);
        }

        return new ResolvedCommand($commandLine, $command, $input);
    }

    /**
     * Takes the real command out of the lazy wrapper Symfony hands back.
     *
     * FrameworkBundle registers commands through a command loader, so find() almost always returns a
     * LazyCommand - and LazyCommand delegates run() but not getSubscribedSignals() or handleSignal().
     * Left wrapped, the wrapper reports subscribing to no signals at all, so the stop the bundle
     * delivers on shutdown lands on nothing: the command runs on until it is force-terminated, and the
     * only clue is a warning about a command that "subscribes to no signals" when it plainly does.
     *
     * getCommand() is what LazyCommand::run() would have called anyway, and it returns a fully
     * initialised command - application, helper set, name and definition all set.
     */
    private function unwrap(Command $command): Command
    {
        return $command instanceof LazyCommand ? $command->getCommand() : $command;
    }

    private function application(): Application
    {
        if ($this->application instanceof Application) {
            return $this->application;
        }

        if (!class_exists(Application::class)) {
            throw CommandNotRunnable::consoleUnavailable();
        }

        $application = new Application($this->kernel);
        // The worker owns the process lifetime, not the command: a command that finished is a worker to
        // be recycled, decided by CommandGroupRunner, and never a process that exits from under swoole.
        $application->setAutoExit(false);
        // Failures have to come back as throwables so they can be logged with the command they came
        // from and counted as a crashed worker, rather than being rendered to stdout and swallowed.
        $application->setCatchExceptions(false);
        $application->setDispatcher(
            // @phpstan-ignore-next-line the container is booted long before any worker starts
            $this->kernel->getContainer()->get('event_dispatcher')
        );

        return $this->application = $application;
    }
}
