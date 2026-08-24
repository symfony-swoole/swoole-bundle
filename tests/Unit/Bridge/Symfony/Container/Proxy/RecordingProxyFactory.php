<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\Proxy;

use Override;
use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\Proxy\AccessInterceptorInterface;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use ProxyManager\Proxy\ValueHolderInterface;
use Swoole\Coroutine\Channel;

/**
 * Stands in for the proxy factory and records when it was inside generating a class.
 *
 * It suspends in the middle, which is the whole point: generating a proxy class reads and writes files,
 * and a coroutine that does I/O is a coroutine the scheduler can take away part-way through. Without
 * that pause a unit test's coroutines run one after another whatever the locking does, and would report
 * a race as absent because nothing gave it the chance to happen.
 */
final class RecordingProxyFactory extends AccessInterceptorValueHolderFactory
{
    /**
     * How long to leave the scheduler somewhere else. Anything above zero is enough - the coroutine
     * waiting to be let in is runnable immediately - and a channel that nobody pushes to is how a
     * coroutine waits for a moment on both engines: OpenSwoole's Coroutine::sleep() takes whole seconds
     * and Coroutine::usleep() does not exist on Swoole.
     */
    private const float SUSPEND_SECONDS = 0.02;

    /**
     * @var list<string> 'enter' and 'leave' in the order they happened
     */
    private array $events = [];

    /**
     * @return list<string>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * The parent's own generics, restated so that what this hands back is still what a proxy factory
     * promises - it only records either side of the call and returns exactly what the parent built.
     *
     * @template RealObjectType of object
     * @param RealObjectType $instance
     * @param array<string, callable> $prefixInterceptors
     * @param array<string, callable> $suffixInterceptors
     * @return AccessInterceptorInterface<RealObjectType>&AccessInterceptorValueHolderInterface<RealObjectType>&RealObjectType&ValueHolderInterface<RealObjectType>
     */
    #[Override]
    public function createProxy(
        $instance,
        array $prefixInterceptors = [],
        array $suffixInterceptors = [],
    ): AccessInterceptorValueHolderInterface {
        $this->events[] = 'enter';

        (new Channel(1))->pop(self::SUSPEND_SECONDS);

        $proxy = parent::createProxy($instance, $prefixInterceptors, $suffixInterceptors);

        $this->events[] = 'leave';

        return $proxy;
    }
}
