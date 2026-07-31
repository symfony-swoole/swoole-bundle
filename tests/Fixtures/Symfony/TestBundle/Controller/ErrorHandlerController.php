<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use ArrayObject;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler\ContextualErrorHandler;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final readonly class ErrorHandlerController
{
    /**
     * Coroutine id used by the handler for everything registered outside of a coroutine.
     */
    private const int GLOBAL_CONTEXT_ID = -1;

    public function __construct(
        private Swoole $swoole,
    ) {}

    /**
     * Overrides the error/exception handler, yields a few times so concurrently handled requests do
     * the very same thing, and finally restores the handlers it registered.
     */
    #[Route(path: '/error-handler/contextual', methods: ['GET'])]
    public function contextual(Request $request): JsonResponse
    {
        $marker = (string) $request->query->get('marker', 'default');
        $sleepMicroseconds = (int) $request->query->get('sleep', 50_000);
        /** @var ArrayObject<int, string> $caught */
        $caught = new ArrayObject();

        $globalErrorHandler = self::currentErrorHandler();
        $globalExceptionHandler = self::currentExceptionHandler();

        set_error_handler(static function (int $type, string $message) use ($caught, $marker): bool {
            $caught->append($marker . ':' . $message);

            return true;
        });
        set_exception_handler(static function (Throwable $throwable) use ($caught, $marker): void {
            $caught->append($marker . ':' . $throwable->getMessage());
        });

        $ownErrorHandler = self::currentErrorHandler();
        $ownExceptionHandler = self::currentExceptionHandler();

        // yield, so the other concurrently handled requests get a chance to override the handlers too
        usleep($sleepMicroseconds);
        trigger_error('warning for ' . $marker, E_USER_WARNING);

        usleep($sleepMicroseconds);
        trigger_error('notice for ' . $marker, E_USER_NOTICE);

        restore_error_handler();
        restore_exception_handler();

        return new JsonResponse([
            'caught' => $caught->getArrayCopy(),
            'coroutine_id' => $this->swoole->getCoroutineId(),
            'global_error_handler' => $globalErrorHandler,
            'global_exception_handler' => $globalExceptionHandler,
            'own_error_handler' => $ownErrorHandler,
            'own_exception_handler' => $ownExceptionHandler,
            'restored_error_handler' => self::currentErrorHandler(),
            'restored_exception_handler' => self::currentExceptionHandler(),
        ]);
    }

    /**
     * Overrides both handlers without ever restoring them, the coroutine end has to clean up after it.
     */
    #[Route(path: '/error-handler/leaking', methods: ['GET'])]
    public function leaking(): JsonResponse
    {
        set_error_handler(static fn(int $type, string $message): bool => true);
        set_exception_handler(static function (Throwable $throwable): void {});

        return $this->current();
    }

    #[Route(path: '/error-handler/current', methods: ['GET'])]
    public function current(): JsonResponse
    {
        return new JsonResponse([
            'coroutine_id' => $this->swoole->getCoroutineId(),
            'error_handler' => self::currentErrorHandler(),
            'exception_handler' => self::currentExceptionHandler(),
        ]);
    }

    /**
     * Exposes the coroutine ids the handler still keeps overrides for, so a test can prove that
     * finished coroutines do not pile up in the handler registry.
     */
    #[Route(path: '/error-handler/tracked-coroutines', methods: ['GET'])]
    public function trackedCoroutines(): JsonResponse
    {
        $instance = (new ReflectionProperty(ContextualErrorHandler::class, 'instance'))->getValue();

        if (!$instance instanceof ContextualErrorHandler) {
            return new JsonResponse(['registered' => false, 'tracked' => []], 200);
        }

        $tracked = [];

        foreach (['errorHandlers', 'exceptionHandlers', 'deferredCleanups'] as $property) {
            /** @var array<int, mixed> $contexts */
            $contexts = (new ReflectionProperty(ContextualErrorHandler::class, $property))->getValue($instance);

            foreach (array_keys($contexts) as $contextId) {
                if ($contextId === self::GLOBAL_CONTEXT_ID) {
                    continue;
                }

                $tracked[$contextId] = $contextId;
            }
        }

        sort($tracked);

        return new JsonResponse([
            'registered' => true,
            'coroutine_id' => $this->swoole->getCoroutineId(),
            'tracked' => $tracked,
        ], 200);
    }

    private static function currentErrorHandler(): string
    {
        $handler = set_error_handler(null);
        restore_error_handler();

        return self::describeHandler($handler);
    }

    private static function currentExceptionHandler(): string
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return self::describeHandler($handler);
    }

    private static function describeHandler(mixed $handler): string
    {
        if (is_array($handler)) {
            $target = $handler[0];

            return (is_object($target) ? $target::class : (string) $target) . '::' . $handler[1];
        }

        return get_debug_type($handler);
    }
}
