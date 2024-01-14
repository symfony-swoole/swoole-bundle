<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Client\Exception;

/**
 * @internal
 */
final class UnsupportedContentTypeException extends \InvalidArgumentException
{
    /**
     * @param string[] $allowed
     */
    public static function forContentType(string $contentType, array $allowed): self
    {
        return new self(sprintf('Content-Type "%s" is not supported. Only "%s" are supported.', $contentType, implode(', ', $allowed)));
    }
}
