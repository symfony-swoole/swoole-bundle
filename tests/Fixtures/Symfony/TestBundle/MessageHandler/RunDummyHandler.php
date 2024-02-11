<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler;

use Co;
use Psr\Log\LoggerInterface;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\RunDummy;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\DummyService;

final class RunDummyHandler
{
    public function __construct(
        private readonly DummyService $dummyService,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(RunDummy $message): void
    {
        $this->logger->info('Run dummy start', [
            'time' => time(),
            'coroutine_id' => Co::getCid(),
        ]);
        $this->dummyService->process();
        $this->logger->info('Run dummy end', [
            'time' => time(),
            'coroutine_id' => Co::getCid(),
        ]);
    }
}
