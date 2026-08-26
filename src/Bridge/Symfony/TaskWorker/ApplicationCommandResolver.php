<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Assert\Assertion;
use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\Exception\CommandNotRunnable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Input\StringInput;
use Throwable;

/**
 * EXPERIMENTAL. Resolves command lines against the application's own console Application.
 *
 * The Application comes from the container and is shared, one per worker. Not pooled: giving each
 * coroutine its own would have every one of them reach the command loader, where
 * ServiceLocatorTrait::get() reads its own re-entry guard as a circular reference if a second
 * coroutine arrives while the first is suspended inside the factory. Shared, only the first resolve
 * consults the loader and the rest find the command already memoized.
 *
 * Note this never calls Application::run(). find() registers the commands by itself, so going straight
 * to the resolved command skips doRunCommand() and with it the console signal registration that would
 * be dead weight inside a swoole worker.
 *
 * Every resolve() has to hand back an instance of its own. Commands keep per-run state on themselves -
 * ConsumeMessagesCommand holds the Worker it is currently running in $worker, which is what
 * handleSignal() stops - so two coroutines sharing one would have the second run overwrite the first's,
 * and the stop meant for one consumer would land on the other while the first could no longer be
 * stopped at all. {@see TaskWorkerProcessor} does that half - it pools those commands and hands each
 * instance its Application - and unwrapping the pool proxy below is the rest.
 */
// phpcs:ignore SlevomatCodingStandard.Classes.ReadonlyClass.ClassCanBeReadonly
final class ApplicationCommandResolver implements CommandResolver
{
    public function __construct(private readonly Application $application) {}

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
                $this->application->find($name)
            );
        } catch (Throwable $throwable) {
            throw CommandNotRunnable::notResolvable($commandLine, $throwable);
        }

        return new ResolvedCommand($commandLine, $command, $input);
    }

    /**
     * Takes the real command out of the lazy wrapper Symfony hands back, and out of the pool proxy
     * behind that.
     *
     * FrameworkBundle registers commands through a command loader, so find() almost always returns a
     * LazyCommand - and LazyCommand delegates run() but not getSubscribedSignals() or handleSignal().
     * Left wrapped, the wrapper reports subscribing to no signals at all, so the stop the bundle
     * delivers on shutdown lands on nothing: the command runs on until it is force-terminated, and the
     * only clue is a warning about a command that "subscribes to no signals" when it plainly does.
     *
     * getCommand() is what LazyCommand::run() would have called anyway. It returns a fully initialised
     * command for the first caller and, once the command is pooled, an instance short of its
     * Application for every caller after that - which is what attachApplication() below is for.
     */
    private function unwrap(Command $command): Command
    {
        if (!$command instanceof LazyCommand) {
            return $this->contextual($command);
        }

        // The wrapper is shared - one instance per command name, however many resolves reach it - and
        // getCommand() writes to it the first time it is called. Several coroutines of a group can be
        // inside that first call at once, because it ends in a container lookup that parks them, so
        // this is not the single write it looks like. It is safe because every one of those writes
        // stores the same object: the closure asks the container for one service id, and what comes
        // back is the pooled proxy where {@see TaskWorkerProcessor} pools the command and the shared
        // instance where it does not.
        return $this->contextual($command->getCommand());
    }

    /**
     * Unwraps the pool proxy, in the coroutine the command is about to run in, so that the instance the
     * proxy would forward to is the one held from here on.
     *
     * It matters for exactly one caller. The watchdog {@see CommandGroupRunner} puts beside each command
     * runs in a coroutine of its own, and calls handleSignal() on the ResolvedCommand built here. Left
     * as a proxy, that call would forward to the watchdog's own pooled instance - one that never ran
     * anything and whose $worker is null - so the stop would land on nothing and the consumer would be
     * force-terminated when max_wait_time expired.
     */
    private function contextual(Command $command): Command
    {
        if (!$command instanceof ContextualProxy) {
            return $command;
        }

        $contextual = $command->getContextualObject();
        Assertion::isInstanceOf($contextual, Command::class);

        return $contextual;
    }
}
