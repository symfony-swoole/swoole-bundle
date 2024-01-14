<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component;

final class ExceptionArrayTransformer
{
    public function transform(\Throwable $exception, string $verbosity = 'default'): array
    {
        return match ($verbosity) {
            'trace' => $this->transformWithFn($exception, $this->transformFnVerboseWithTrace(...)),
            'verbose' => $this->transformWithFn($exception, $this->transformFnVerbose(...)),
            default => $this->transformWithFn($exception, $this->transformFnDefault(...)),
        };
    }

    private function transformWithFn(\Throwable $exception, callable $transformer): array
    {
        $data = $transformer($exception);

        $previous = $exception->getPrevious();

        if ($previous instanceof \Throwable) {
            $data['previous'] = $transformer($previous);
        }

        return $data;
    }

    private function transformFnDefault(\Throwable $exception): array
    {
        return [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ];
    }

    private function transformFnVerbose(\Throwable $exception): array
    {
        return [
            'class' => $exception::class,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }

    private function transformFnVerboseWithTrace(\Throwable $exception): array
    {
        return [
            'class' => $exception::class,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => array_map(function (array $trace): array {
                $trace['args'] = \array_key_exists('args', $trace) ? $this->transformTraceArgs($trace['args']) : null;

                return $trace;
            }, $exception->getTrace()),
        ];
    }

    private function transformTraceArgs(array $args): array
    {
        return array_map(fn ($arg): string => get_debug_type($arg), $args);
    }
}
