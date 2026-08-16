<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\Exception;

use RuntimeException;
use Throwable;

final class CommandNotRunnable extends RuntimeException
{
    public static function notResolvable(string $commandLine, Throwable $previous): self
    {
        return new self(
            sprintf('Task worker command "%s" could not be resolved: %s', $commandLine, $previous->getMessage()),
            0,
            $previous,
        );
    }

    public static function empty(): self
    {
        return new self('Task worker command line is empty.');
    }

    public static function consoleUnavailable(): self
    {
        return new self(
            'Running console commands in task workers requires symfony/framework-bundle, which provides '
            . 'the console Application that resolves them.',
        );
    }
}
