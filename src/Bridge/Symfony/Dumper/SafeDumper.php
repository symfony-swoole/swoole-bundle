<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Dumper;

use Assert\Assertion;
use SwooleBundle\SwooleBundle\Bridge\CommonSwoole\SystemSwooleFactory;
use SwooleBundle\SwooleBundle\Component\Concurrency\SerialQueue;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Dumping from several coroutines at once corrupts the output of the var dumper, which writes to a shared
 * stream. All dumps are therefore replayed one at a time by a single consumer coroutine.
 */
final class SafeDumper extends VarDumper
{
    private const int QUEUE_CAPACITY = 100;

    private static SerialQueue|null $queue = null;

    public static function dump(mixed $var, ?string $label = null): mixed
    {
        self::queue()->submit([$var, $label]);

        return null;
    }

    /**
     * Writes out everything dumped so far, so nothing is lost when the process is about to go away.
     */
    public static function flush(): void
    {
        self::$queue?->drain();
    }

    private static function queue(): SerialQueue
    {
        return self::$queue ??= new SerialQueue(
            SystemSwooleFactory::newFactoryInstance()->newInstance(),
            static function (mixed $item): void {
                Assertion::isArray($item);
                [$var, $label] = $item;
                Assertion::nullOrString($label);

                parent::dump($var, $label);
            },
            self::QUEUE_CAPACITY,
        );
    }
}
