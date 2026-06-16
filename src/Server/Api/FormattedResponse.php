<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Api;

final readonly class FormattedResponse
{
    public function __construct(
        private string $contentType,
        private string $body,
    ) {}

    public function contentType(): string
    {
        return $this->contentType;
    }

    public function body(): string
    {
        return $this->body;
    }
}
