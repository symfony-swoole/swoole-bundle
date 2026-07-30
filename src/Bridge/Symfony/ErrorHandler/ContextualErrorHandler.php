<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler;

use Co;
use ReflectionFunction as NativeReflectionFunction;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use Throwable;
use ZEngine\Reflection\ReflectionFunction;

/**
 * Coroutine aware replacement of the PHP error and exception handler registry.
 *
 * PHP keeps a single, process wide stack of error/exception handlers. With coroutines enabled that
 * stack is shared by all requests handled concurrently in the same worker, so any code temporarily
 * overriding a handler (and restoring it afterwards) silently corrupts the handler of every other
 * coroutine running in the meantime.
 *
 * Once registered, set_error_handler(), set_exception_handler(), restore_error_handler() and
 * restore_exception_handler() are redefined to keep a separate handler stack per coroutine.
 * Handlers registered outside of a coroutine (for example during kernel boot) form the global
 * stack, which is used as a fallback by every coroutine that did not override them.
 */
final class ContextualErrorHandler
{
    /**
     * Coroutine id reported when running outside of a coroutine.
     */
    private const int GLOBAL_CONTEXT_ID = -1;

    private static ?self $instance = null;

    /**
     * @var array<int, list<array{0: callable|null, 1: int}>>
     */
    private array $errorHandlers = [];

    /**
     * @var array<int, list<callable|null>>
     */
    private array $exceptionHandlers = [];

    /**
     * @var array<int, bool>
     */
    private array $deferredCleanups = [];

    private function __construct(
        private readonly Swoole $swoole,
    ) {}

    /**
     * Takes over the PHP error/exception handler registry. Handlers registered before this call are
     * kept as the global fallback. Calling it more than once is a no-op.
     */
    public static function register(Swoole $swoole, int $errorLevels = E_ALL): void
    {
        if (self::$instance !== null) {
            return;
        }

        $instance = new self($swoole);
        self::$instance = $instance;

        $previousErrorHandler = set_error_handler([$instance, 'handleError'], $errorLevels);
        $previousExceptionHandler = set_exception_handler([$instance, 'handleException']);

        if ($previousErrorHandler !== null) {
            $instance->setErrorHandler($previousErrorHandler, $errorLevels);
        }

        if ($previousExceptionHandler !== null) {
            $instance->setExceptionHandler($previousExceptionHandler);
        }

        self::redefineHandlerFunctions();
    }

    /**
     * Registered as the one and only real PHP error handler, dispatches to the handler of the
     * current coroutine.
     */
    public function handleError(int $type, string $message, string $file, int $line): bool
    {
        [$handler, $errorLevels] = $this->currentErrorHandler();

        if ($handler === null || ($errorLevels & $type) === 0) {
            // let the internal PHP error handler take over
            return false;
        }

        return $handler($type, $message, $file, $line) !== false;
    }

    /**
     * Registered as the one and only real PHP exception handler, dispatches to the handler of the
     * current coroutine.
     */
    public function handleException(Throwable $exception): void
    {
        $handler = $this->currentExceptionHandler();

        if ($handler === null) {
            throw $exception;
        }

        $handler($exception);
    }

    /**
     * @return callable|null the handler which was effective for the current coroutine before
     */
    public function setErrorHandler(?callable $callback, int $errorLevels = E_ALL): ?callable
    {
        $contextId = $this->contextId();
        [$previous] = $this->currentErrorHandler();
        $this->errorHandlers[$contextId][] = [$callback, $errorLevels];
        $this->deferContextCleanup($contextId);

        return $previous;
    }

    /**
     * A coroutine can only restore handlers it registered itself, it can never pop the global stack.
     */
    public function restoreErrorHandler(): void
    {
        $contextId = $this->contextId();

        if (($this->errorHandlers[$contextId] ?? []) === []) {
            return;
        }

        array_pop($this->errorHandlers[$contextId]);
    }

    public function getErrorHandler(): ?callable
    {
        return $this->currentErrorHandler()[0];
    }

    /**
     * @return callable|null the handler which was effective for the current coroutine before
     */
    public function setExceptionHandler(?callable $callback): ?callable
    {
        $contextId = $this->contextId();
        $previous = $this->currentExceptionHandler();
        $this->exceptionHandlers[$contextId][] = $callback;
        $this->deferContextCleanup($contextId);

        return $previous;
    }

    /**
     * A coroutine can only restore handlers it registered itself, it can never pop the global stack.
     */
    public function restoreExceptionHandler(): void
    {
        $contextId = $this->contextId();

        if (($this->exceptionHandlers[$contextId] ?? []) === []) {
            return;
        }

        array_pop($this->exceptionHandlers[$contextId]);
    }

    public function getExceptionHandler(): ?callable
    {
        return $this->currentExceptionHandler();
    }

    private static function instance(): self
    {
        if (self::$instance === null) {
            throw UnregisteredContextualErrorHandler::notRegisteredYet();
        }

        return self::$instance;
    }

    /**
     * The redefined closures have to be signature compatible with the functions they replace,
     * which is why the original parameter names are used here.
     */
    private static function redefineHandlerFunctions(): void
    {
        // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter
        (new ReflectionFunction('set_error_handler'))->redefine(
            static fn(?callable $callback, int $error_levels = E_ALL) // phpcs:ignore
                => self::instance()->setErrorHandler($callback, $error_levels),
        );

        (new ReflectionFunction('set_exception_handler'))->redefine(
            static fn(?callable $callback) => self::instance()->setExceptionHandler($callback),
        );
        // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter

        (new ReflectionFunction('restore_error_handler'))->redefine(static function (): true {
            self::instance()->restoreErrorHandler();

            return true;
        });

        (new ReflectionFunction('restore_exception_handler'))->redefine(static function (): true {
            self::instance()->restoreExceptionHandler();

            return true;
        });

        // php 8.5+ provides these natively, on older versions the symfony polyfill implements them
        // on top of the set_*()/restore_*() pair, which is contextual already
        if (self::isNativeFunction('get_error_handler')) {
            (new ReflectionFunction('get_error_handler'))->redefine(
                static fn(): ?callable => self::instance()->getErrorHandler(),
            );
        }

        if (!self::isNativeFunction('get_exception_handler')) {
            return;
        }

        (new ReflectionFunction('get_exception_handler'))->redefine(
            static fn(): ?callable => self::instance()->getExceptionHandler(),
        );
    }

    private static function isNativeFunction(string $functionName): bool
    {
        return function_exists($functionName) && (new NativeReflectionFunction($functionName))->isInternal();
    }

    /**
     * @return array{0: callable|null, 1: int}
     */
    private function currentErrorHandler(): array
    {
        $stack = $this->errorHandlerStack();

        return $stack === [] ? [null, 0] : $stack[array_key_last($stack)];
    }

    private function currentExceptionHandler(): ?callable
    {
        $contextId = $this->contextId();
        $stack = $this->exceptionHandlers[$contextId] ?? [];

        if ($stack === [] && $contextId !== self::GLOBAL_CONTEXT_ID) {
            $stack = $this->exceptionHandlers[self::GLOBAL_CONTEXT_ID] ?? [];
        }

        return $stack === [] ? null : $stack[array_key_last($stack)];
    }

    /**
     * @return list<array{0: callable|null, 1: int}>
     */
    private function errorHandlerStack(): array
    {
        $contextId = $this->contextId();
        $stack = $this->errorHandlers[$contextId] ?? [];

        if ($stack === [] && $contextId !== self::GLOBAL_CONTEXT_ID) {
            return $this->errorHandlers[self::GLOBAL_CONTEXT_ID] ?? [];
        }

        return $stack;
    }

    /**
     * Drops the overrides of a coroutine when it finishes, so the next coroutine reusing the same id
     * starts from the global handlers again. Registered as late as possible - the very first time a
     * coroutine overrides a handler - which covers coroutines started by a plain go() as well.
     */
    private function deferContextCleanup(int $contextId): void
    {
        if ($contextId === self::GLOBAL_CONTEXT_ID || isset($this->deferredCleanups[$contextId])) {
            return;
        }

        $this->deferredCleanups[$contextId] = true;

        Co::defer(function (): void {
            $this->clearCurrentContext();
        });
    }

    private function clearCurrentContext(): void
    {
        $contextId = $this->contextId();

        if ($contextId === self::GLOBAL_CONTEXT_ID) {
            return;
        }

        unset(
            $this->errorHandlers[$contextId],
            $this->exceptionHandlers[$contextId],
            $this->deferredCleanups[$contextId],
        );
    }

    private function contextId(): int
    {
        return $this->swoole->getCoroutineId();
    }
}
