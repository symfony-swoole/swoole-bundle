<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Client\Exception;

use InvalidArgumentException;

/**
 * @internal
 */
final class MissingContentTypeException extends InvalidArgumentException
{
    public static function make(): self
    {
        return new self('Server response did not contain mandatory header "Content-Type".');
    }
}
