<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Interceptor;

use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;

/**
 * Manages and executes a chain of interceptors.
 */
final class InterceptorChain
{
    /**
     * @var array<Interceptor>
     */
    private array $interceptors = [];

    /**
     * @param iterable<Interceptor> $interceptors
     */
    public function __construct(iterable $interceptors = [])
    {
        foreach ($interceptors as $interceptor) {
            $this->addInterceptor($interceptor);
        }
    }

    /**
     * Add an interceptor to the chain.
     */
    public function addInterceptor(Interceptor $interceptor): self
    {
        $this->interceptors[] = $interceptor;

        return $this;
    }

    /**
     * Execute the interceptor chain with the given handler.
     *
     * @param callable $handler The final handler to execute
     */
    public function execute(Context $context, callable $handler): Context
    {
        // Sort interceptors by priority (higher priority first)
        $sortedInterceptors = $this->getSortedInterceptors();

        // Build the chain from the end backwards
        $chain = $handler;

        foreach (array_reverse($sortedInterceptors) as $interceptor) {
            $chain = static fn(Context $ctx) => $interceptor->intercept($ctx, $chain);
        }

        return $chain($context);
    }

    /**
     * Get interceptors sorted by priority (descending).
     *
     * @return array<Interceptor>
     */
    private function getSortedInterceptors(): array
    {
        $interceptors = $this->interceptors;
        usort($interceptors, static fn(Interceptor $a, Interceptor $b) => $b->getPriority() <=> $a->getPriority());

        return $interceptors;
    }
}
