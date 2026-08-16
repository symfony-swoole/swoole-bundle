<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

/**
 * EXPERIMENTAL. Turns a configured command line into something a task worker can run.
 */
interface CommandResolver
{
    /**
     * @param string $commandLine as written in configuration, e.g. 'messenger:consume default -vv'
     * @throws Exception\CommandNotRunnable
     */
    public function resolve(string $commandLine): ResolvedCommand;
}
