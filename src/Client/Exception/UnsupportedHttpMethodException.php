<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Client\Exception;

use InvalidArgumentException;

/**
 * @internal
 */
final class UnsupportedHttpMethodException extends InvalidArgumentException
{
    /**
     * @param array<string> $allowed
     */
    public static function forMethod(string $method, array $allowed): self
    {
        return new self(
            sprintf('Http method "%s" is not supported. Only "%s" are supported.', $method, implode(', ', $allowed))
        );
    }
}
