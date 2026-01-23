<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Interceptor;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;
use Throwable;

/**
 * Logging interceptor for gRPC method calls.
 *
 * Logs request/response information and execution time.
 */
final readonly class LoggingInterceptor implements Interceptor
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
        private int $priority = 100,
    ) {
    }

    public function intercept(Context $context, callable $next): Context
    {
        $startTime = microtime(true);
        $service = $context->getRequest()->getService();
        $method = $context->getRequest()->getMethod();

//        $this->logger->info('gRPC call started', [
//            'service' => $service,
//            'method' => $method,
//            'content_type' => $context->getRequest()->getContentType(),
//        ]);

        try {
            $context = $next($context);

            $duration = microtime(true) - $startTime;

            $this->logger->info('gRPC call completed', [
                'service' => $service,
                'method' => $method,
                'status' => $context->getResponse()->getStatus(),
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $context;
        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;

            //todo: trace only on dev env
            $this->logger->error('gRPC call failed', [
                'service' => $service,
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round($duration * 1000, 2),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
