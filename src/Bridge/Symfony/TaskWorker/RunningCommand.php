<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use ArrayObject;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

/**
 * Which console command the code running here belongs to, for a log line to say.
 *
 * This is the part a log file per worker could not give you. A task worker runs a whole group of
 * commands side by side, each in a coroutine of its own - two messenger consumers, say, or two
 * projection runners differing only in a `--group` - so the process says nothing about which of them
 * wrote a line. The coroutine does.
 *
 * ### Kept in the coroutine's own context, and looked up through its ancestors
 *
 * The command line is stored in the coroutine's context rather than in a field here, because a field
 * would be the commands of a group overwriting each other - which is wrong on its own, and fatal under
 * fiber context checking, where a second coroutine writing a property another one wrote is an error.
 *
 * A coroutine does not inherit its parent's context, so a lookup walks up the parent chain until it
 * finds a command or runs out. That is what covers the coroutines a command spawns rather than runs in,
 * which is most of the interesting work: a consumer handling a message in a coroutine of its own is
 * still `messenger:consume`.
 *
 * ### And a field for the command that started before any coroutine did
 *
 * Inside a task worker with coroutines on, the command is already in a coroutine when it starts. With
 * coroutines off there is no scheduler to spawn into, and the same is true of a command run from a
 * supervisor instead - `bin/console messenger:consume` - where {@see self::recordConsoleCommand()}
 * fires while there is still nothing to remember it in. Those get the field, which is safe because a
 * process outside a coroutine is running exactly one command.
 *
 * {@see self::forget()} is what keeps the field honest across a fork: every worker of a server
 * inherits `swoole:server:run` from the master it was forked from, and none of what a worker goes on to
 * do should be attributed to it.
 */
final class RunningCommand
{
    /**
     * Namespaced, because a coroutine's context is shared with everything else running in it.
     */
    private const string CONTEXT_KEY = 'swoole_bundle.running_command';

    /**
     * How far up the parent chain to look. Generous next to what a worker actually nests, and bounded
     * because a walk over a chain that somehow loops would take the worker with it.
     */
    private const int MAX_ANCESTORS = 16;

    private ?string $processCommandLine = null;

    public function __construct(private readonly Swoole $swoole) {}

    /**
     * @param string $commandLine as the command was asked for, e.g. 'messenger:consume default -vv'
     */
    public function record(string $commandLine): void
    {
        $context = $this->contextOfTheRunningCoroutine();

        if ($context === null) {
            $this->processCommandLine = $commandLine;

            return;
        }

        $context[self::CONTEXT_KEY] = $commandLine;
    }

    /**
     * The name only, and deliberately not the input it was given. A command line typed at a shell can
     * carry anything - a token in an argument, a path someone would rather not publish - and this ends
     * up in every log line the command writes. What a task worker runs is named in configuration
     * instead, so there it is recorded whole.
     */
    public function recordConsoleCommand(ConsoleCommandEvent $event): void
    {
        $name = $event->getCommand()?->getName();

        if ($name === null) {
            return;
        }

        $this->record($name);
    }

    /**
     * Tagged as the listener of a worker having started, which passes the event; nothing here needs it,
     * since the fact that a worker started is the whole of the news.
     */
    public function forget(): void
    {
        $this->processCommandLine = null;
    }

    /**
     * @return string|null the command this coroutine is running, or the one that spawned it, or null
     *         when nothing above it is a command - an http request, say
     */
    public function current(): ?string
    {
        $cid = $this->runningCoroutineId();

        for ($step = 0; $step < self::MAX_ANCESTORS && $cid !== null; $step++) {
            $commandLine = $this->recordedIn($cid);

            if ($commandLine !== null) {
                return $commandLine;
            }

            $cid = $this->parentOf($cid);
        }

        return $this->processCommandLine;
    }

    private function recordedIn(int $cid): ?string
    {
        $context = $this->swoole->getCoroutineContext($cid);

        if ($context === null) {
            return null;
        }

        /** @var mixed $commandLine */
        $commandLine = $context[self::CONTEXT_KEY] ?? null;

        return is_string($commandLine) ? $commandLine : null;
    }

    private function parentOf(int $cid): ?int
    {
        $parent = $this->swoole->getParentCoroutineId($cid);

        return $parent > 0 ? $parent : null;
    }

    /**
     * @return ArrayObject<array-key, mixed>|null
     */
    private function contextOfTheRunningCoroutine(): ?ArrayObject
    {
        $cid = $this->runningCoroutineId();

        return $cid === null ? null : $this->swoole->getCoroutineContext($cid);
    }

    /**
     * Null outside a coroutine, which the engines answer -1 for.
     */
    private function runningCoroutineId(): ?int
    {
        $cid = $this->swoole->getCoroutineId();

        return $cid > 0 ? $cid : null;
    }
}
