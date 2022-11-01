<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\MessageHandler;

use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Message\SleepAndAppend;
use Psr\Log\LoggerInterface;

final class SleepAndAppendHandler
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(SleepAndAppend $message): void
    {
        $this->logger->info('Sleep and append start', [
            'time' => time(),
            'coroutine_id' => \Co::getCid(),
            'text' => $message->getAppend(),
        ]);
        usleep($message->getSleepMs() * 1000);
        file_put_contents($message->getFileName(), $message->getAppend().PHP_EOL, FILE_APPEND);
        $this->logger->info('Sleep and append end', [
            'time' => time(),
            'coroutine_id' => \Co::getCid(),
            'text' => $message->getAppend(),
        ]);
    }
}
