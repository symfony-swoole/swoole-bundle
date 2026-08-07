<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler;

use RuntimeException;
use Throwable;

final class UnregisteredContextualErrorHandler extends RuntimeException
{
    private function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function notRegisteredYet(): self
    {
        return new self('ContextualErrorHandler was not registered yet.');
    }
}
