<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Message;

final class SleepAndAppend
{
    public function __construct(
        private string $fileName,
        private int $sleepMs,
        private string $append
    ) {
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getSleepMs(): int
    {
        return $this->sleepMs;
    }

    public function getAppend(): string
    {
        return $this->append;
    }
}
