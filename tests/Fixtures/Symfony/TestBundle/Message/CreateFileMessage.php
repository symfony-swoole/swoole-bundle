<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Message;

final class CreateFileMessage
{
    public function __construct(
        private readonly string $fileName,
        private readonly string $content
    ) {
    }

    public function fileName(): string
    {
        return $this->fileName;
    }

    public function content(): string
    {
        return $this->content;
    }
}
