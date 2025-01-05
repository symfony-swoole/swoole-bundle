<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container;

use Co;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;

final class CoWrapper
{
    private static ?self $instance; /** @phpstan-ignore property.unusedType */

    public function __construct(
        private readonly ServicePoolContainer $servicePoolContainer,
        private readonly Swoole $swoole,
    ) {
        self::$instance = $this;
    }

    public function defer(): void
    {
        Co::defer(function (): void {
            $this->servicePoolContainer->releaseFromCoroutine($this->swoole->getCoroutineId());
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
        if (self::$instance === null) {
            throw UsageBeforeInitialization::notInitializedYet();
        }

        return self::$instance;
    }
}
