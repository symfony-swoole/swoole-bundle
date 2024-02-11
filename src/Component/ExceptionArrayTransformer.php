<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component;

use Throwable;

/**
 * @phpstan-type DefaultExceptionArray = array{
 *   code: int,
 *   message: string,
 * }
 * @phpstan-type VerboseExceptionArray = array{
 *   class: class-string<Throwable>&literal-string,
 *   code: int,
 *   message: string,
 *   file: string,
 *   line: int,
 * }
 * @phpstan-type VerboseWithTraceExceptionArray = array{
 *   class: class-string<Throwable>&literal-string,
 *   code: int,
 *   message: string,
 *   file: string,
 *   line: int,
 *   trace: array<int, array{
 *     function: string,
 *     line?: int,
 *     file?: string,
 *     class?: class-string,
 *     type?: '->'|'::',
 *     args: array<string>|null,
 *     object?: object
 *   }>,
 * }
 * @phpstan-type ExceptionArray = DefaultExceptionArray|VerboseExceptionArray|VerboseWithTraceExceptionArray
 */
final class ExceptionArrayTransformer
{
    /**
     * @return array{previous?: ExceptionArray}&ExceptionArray
     */
    public function transform(Throwable $exception, string $verbosity = 'default'): array
    {
        return match ($verbosity) {
            'trace' => $this->transformWithFn($exception, $this->transformFnVerboseWithTrace(...)),
            'verbose' => $this->transformWithFn($exception, $this->transformFnVerbose(...)),
            default => $this->transformWithFn($exception, $this->transformFnDefault(...)),
        };
    }

    /**
     * @param callable(Throwable): ExceptionArray $transformer
     * @return array{previous?: ExceptionArray}&ExceptionArray
     */
    private function transformWithFn(Throwable $exception, callable $transformer): array
    {
        $data = $transformer($exception);

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            $data['previous'] = $transformer($previous);
        }

        return $data;
    }

    /**
     * @return DefaultExceptionArray
     */
    private function transformFnDefault(Throwable $exception): array
    {
        return [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ];
    }

    /**
     * @return VerboseExceptionArray
     */
    private function transformFnVerbose(Throwable $exception): array
    {
        return [
            'class' => $exception::class,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }

    /**
     * @return VerboseWithTraceExceptionArray
     */
    private function transformFnVerboseWithTrace(Throwable $exception): array
    {
        return [
            'class' => $exception::class,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => array_map(function (array $trace): array {
                $trace['args'] = array_key_exists('args', $trace) ? $this->transformTraceArgs($trace['args']) : null;

                return $trace;
            }, $exception->getTrace()),
        ];
    }

    /**
     * @param array<mixed> $args
     * @return array<string>
     */
    private function transformTraceArgs(array $args): array
    {
        return array_map(static fn($arg): string => get_debug_type($arg), $args);
    }
}
