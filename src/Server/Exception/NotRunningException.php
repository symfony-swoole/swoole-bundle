<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Exception;

use RuntimeException;

/**
 * @internal
 */
final class NotRunningException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Swoole HTTP Server has not been running.');
    }
}
