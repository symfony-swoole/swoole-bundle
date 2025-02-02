<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler;

use Co;
use Psr\Log\LoggerInterface;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\SleepAndAppend;

final readonly class SleepAndAppendHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(SleepAndAppend $message): void
    {
        $this->logger->info('Sleep and append start', [
            'time' => time(),
            'coroutine_id' => Co::getCid(),
            'text' => $message->getAppend(),
        ]);
        usleep($message->getSleepMs() * 1000);
        file_put_contents($message->getFileName(), $message->getAppend() . PHP_EOL, FILE_APPEND);
        $this->logger->info('Sleep and append end', [
            'time' => time(),
            'coroutine_id' => Co::getCid(),
            'text' => $message->getAppend(),
        ]);
    }
}
