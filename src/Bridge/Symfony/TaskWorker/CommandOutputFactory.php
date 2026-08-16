<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * EXPERIMENTAL. Builds the output a task worker command writes to.
 */
interface CommandOutputFactory
{
    public function newOutput(string $commandLine): OutputInterface;
}
