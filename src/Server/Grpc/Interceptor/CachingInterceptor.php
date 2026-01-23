<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Interceptor;

use Psr\SimpleCache\CacheInterface;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;
use SwooleBundle\SwooleBundle\Server\Grpc\Status;

/**
 * Just for demonstration purposes, not used, tested - will be removed.
 *
 * Caching interceptor for gRPC unary method calls.
 *
 * Caches responses based on service, method, and request payload hash.
 * Only caches successful responses (status OK).
 */
final readonly class CachingInterceptor implements Interceptor
{
    public function __construct(
        private CacheInterface $cache,
        private int $ttl = 300, // 5 minutes default
        private int $priority = 50,
    ) {
    }

    public function intercept(Context $context, callable $next): Context
    {
        $cacheKey = $this->generateCacheKey($context);

        // Try to get cached response
        $cachedPayload = $this->cache->get($cacheKey);

        if ($cachedPayload !== null) {
            $context->getResponse()
                ->setPayload($cachedPayload)
                ->withStatus(Status::OK)
                ->withMessage('OK');

            // Mark as cached in context attributes
            $context->withAttribute('cache_hit', true);

            return $context;
        }

        // Execute the call
        $context = $next($context);

        // Cache successful responses only
        if ($context->getResponse()->getStatus() === Status::OK) {
            $payload = $context->getResponse()->getPayload();
            if ($payload !== null && $payload !== '') {
                $this->cache->set($cacheKey, $payload, $this->ttl);
            }
        }

        $context->withAttribute('cache_hit', false);

        return $context;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Generate a cache key based on service, method, and payload.
     */
    private function generateCacheKey(Context $context): string
    {
        $service = $context->getRequest()->getService();
        $method = $context->getRequest()->getMethod();
        $payload = $context->getRequest()->getPayload();

        $payloadHash = hash('xxh3', $payload ?? '');

        return sprintf('grpc:%s:%s:%s', $service, $method, $payloadHash);
    }
}
