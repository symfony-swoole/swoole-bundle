<?php

// this is an override of the extension class and will only work when the extension is not installed
// (e.g. in CI pipelines)

namespace {
    if (class_exists(Tideways\Profiler::class)) {
        return;
    }
}

namespace Tideways {
    class Profiler
    {
        public static bool $wasStarted = false;

        public static bool $wasStopped = false;

        private function __construct()
        {
        }

        public static function isStarted(): bool
        {
            return true;
        }

        public static function isTracing(): bool
        {
            return false;
        }

        public static function isProfiling(): bool
        {
            return false;
        }

        public static function containsDeveloperTraceRequest(): bool
        {
            return false;
        }

        /**
         * @psalm-param string|array{api_key?: string, service?: string, sample_rate?: int} $options
         */
        public static function start(array|string $options): void
        {
            self::$wasStarted = true;
        }

        public static function stop(): void
        {
            self::$wasStopped = true;
        }

        public static function ignoreTransaction(): void
        {
        }

        /**
         * @param null|array<string> $trace
         */
        public static function logFatal(string $message, string $file, int $line, ?string $type = null, ?array $trace = null): void
        {
        }

        public static function logException(\Throwable $exception): void
        {
        }

        public static function getTransactionName(): ?string
        {
            return null;
        }

        public static function setTransactionName(string $transactionName): void
        {
        }

        public static function detectTransactionFunction(string $functionName): void
        {
        }

        public static function detectExceptionFunction(string $functionName): void
        {
        }

        public static function triggerCallgraphOn(string $functionName): void
        {
        }

        public static function watch(string $functionName): void
        {
        }

        public static function watchCallback(string $functionName, callable $callback): void
        {
        }

        public static function enableCallgraphProfiler(): bool
        {
            return true;
        }

        public static function disableCallgraphProfiler(): bool
        {
            return true;
        }

        public static function enableTracingProfiler(): bool
        {
            return true;
        }

        public static function disableTracingProfiler(): bool
        {
            return true;
        }

        public static function addEventMarker(string $eventName): void
        {
        }

        /**
         * @param null|bool|float|int|object|string $value
         */
        public static function setCustomVariable(string $name, $value): void
        {
        }

        public static function currentTraceId(): ?string
        {
            return null;
        }

        public static function setServiceName(string $serviceName): void
        {
        }

        public static function createSpan(string $category): Profiler\Span
        {
            return new Profiler\Span();
        }

        public static function generateServerTimingHeaderValue(): string
        {
            return 'header';
        }

        /** @since tideways 5.5.6 **/
        public static function markPageCacheHit(): void
        {
        }

        /** @since tideways 5.5.6 **/
        public static function markPageCacheMiss(): void
        {
        }

        public static function markAsWebTransaction(): void
        {
        }
    }
}

namespace Tideways\Profiler {
    class Span
    {
        public function __construct()
        {
        }

        public function getId(): string
        {
            return 'id';
        }

        /**
         * @param array<string,bool|int|string> $annotations
         */
        public function annotate(array $annotations): void
        {
        }

        public function logException(\Throwable $exception): void
        {
        }

        public function finish(): void
        {
        }
    }
}
