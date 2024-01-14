<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container;

final class UsageBeforeInitialization extends \RuntimeException
{
    private function __construct(string $message = '', int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function notInitializedYet(): self
    {
        return new self('CoWrapper was not initialised yet.');
    }
}
