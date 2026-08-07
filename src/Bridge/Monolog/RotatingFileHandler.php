<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Monolog;

use Monolog\Handler\RotatingFileHandler as OriginalRotatingFileHandler;

/**
 * Coroutine safe drop-in replacement for the Monolog rotating file handler, see {@see CoroutineSafeWrites}.
 *
 * Rotation itself is covered as well: the original write() closes the handler to trigger a rotation, and
 * since it only ever runs inside the consumer coroutine, the rotation cannot race concurrent writes.
 */
final class RotatingFileHandler extends OriginalRotatingFileHandler
{
    use CoroutineSafeWrites;
}
