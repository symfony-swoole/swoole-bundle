<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Monolog;

use Monolog\Handler\StreamHandler as OriginalStreamHandler;

/**
 * Coroutine safe drop-in replacement for the Monolog stream handler, see {@see CoroutineSafeWrites}.
 */
final class StreamHandler extends OriginalStreamHandler
{
    use CoroutineSafeWrites;
}
