<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Dumper;

use Assert\Assertion;
use Swoole\Coroutine\Channel;
use Symfony\Component\VarDumper\VarDumper;

final class SafeDumper extends VarDumper
{
    private static bool $isInitialized = false;

    private static bool $isConsumerRunning = false;

    private static Channel $channel;

    public static function dump(mixed $var, ?string $label = null): mixed
    {
        self::publish($var, $label);

        return null;
    }

    private static function publish(mixed $var, ?string $label = null): void
    {
        self::initialize();
        $item = [$var, $label];

        self::$channel->push($item);
        self::runDumpingConsumer();
    }

    private static function initialize(): void
    {
        if (self::$isInitialized) {
            return;
        }

        self::$channel = new Channel(100);
        self::$isInitialized = true;
    }

    private static function runDumpingConsumer(): void
    {
        if (self::$isConsumerRunning) {
            return;
        }

        self::$isConsumerRunning = true;

        go(static function (): void {
            while (!self::$channel->isEmpty()) {
                $item = self::$channel->pop();

                Assertion::isArray($item);
                [$var, $label] = $item;
                Assertion::nullOrString($label);

                parent::dump($var, $label);
            }

            self::$isConsumerRunning = false;
        });
    }
}
