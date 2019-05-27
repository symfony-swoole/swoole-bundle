<?php
declare(strict_types=1);

/*
 * @author Martin Fris <rasta@lj.sk>
 */

namespace K911\Swoole\Bridge\Symfony\Logging;

use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Channel;
use Symfony\Bundle\FrameworkBundle\Command\CacheClearCommand;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

/**
 *
 */
final class MasterLogger
{
    /**
     * @var Channel
     */
    private $channel;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $active = false;

    /**
     * @param Channel         $channel
     * @param LoggerInterface $logger
     */
    public function __construct(Channel $channel, LoggerInterface $logger)
    {
        $this->channel = $channel;
        $this->logger  = $logger;
    }

    public function onInitialize(ConsoleCommandEvent $event): void
    {
        if ($event->getCommand() instanceof CacheClearCommand) {
            // on cache clear command the teminate events somehow disappear from the event dispatcher
            // so it is better to not run the channel logger in such case
            // because it would get stuck, waiting to be closed on app exit
            return;
        }

        $this->start();
    }

    /**
     * @return void
     */
    public function onTerminate(): void
    {
        $this->stop();
    }

    /**
     * @return void
     */
    private function stop(): void
    {
        $this->active = false;
        $this->channel->close();
    }

    /**
     *
     */
    private function start(): void
    {
        $this->active = true;

        go(function () {
            $this->channelReadLoop();
        });
    }

    /**
     * @return void
     */
    private function channelReadLoop(): void
    {
        while (true) {
            $logData = $this->channel->pop();

            if ($logData === false) {
                if (!$this->active) {
                    break;
                }
                usleep(1000);
                continue;
            }

            $this->processLog($logData);
        }
    }

    /**
     * @param array $logData
     *
     * @return void
     */
    private function processLog(array $logData): void
    {
        $this->logger->log($logData['level'], $logData['message'], $logData['context']);
    }
}
