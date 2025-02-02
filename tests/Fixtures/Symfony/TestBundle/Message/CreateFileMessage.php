<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message;

final readonly class CreateFileMessage
{
    public function __construct(
        private string $fileName,
        private string $content,
    ) {}

    public function fileName(): string
    {
        return $this->fileName;
    }

    public function content(): string
    {
        return $this->content;
    }
}
