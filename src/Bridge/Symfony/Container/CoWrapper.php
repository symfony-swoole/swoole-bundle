<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container;

use Co;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;

final class CoWrapper
{
    private static ?self $instance;

    public function __construct(private readonly ServicePoolContainer $servicePoolContainer)
    {
        self::$instance = $this;
    }

    public function defer(): void
    {
        \Co::defer(function (): void {
            $this->servicePoolContainer->releaseFromCoroutine(\Co::getCid());
        });
    }

    /**
     * instead of Co::go(), CoWrapper::go() has to be used to run coroutines in Symfony apps, so Symfony
     * is able to reset all stateful service instances.
     *
     * @SuppressWarnings(PHPMD.ShortMethodName)
     */
    public static function go(callable $fn): void
    {
        go(static function () use ($fn): void {
            self::getInstance()->defer();
            $fn();
        });
    }

    private static function getInstance(): self
    {
        if (null === self::$instance) {
            throw UsageBeforeInitialization::notInitializedYet();
        }

        return self::$instance;
    }
}
