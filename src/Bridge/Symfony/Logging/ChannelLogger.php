<?php
declare(strict_types=1);

/*
 * @author Martin Fris <rasta@lj.sk>
 */

namespace K911\Swoole\Bridge\Symfony\Logging;

use Psr\Log\AbstractLogger;
use Swoole\Coroutine\Channel;

/**
 *
 */
final class ChannelLogger extends AbstractLogger
{
    /**
     * @var Channel
     */
    private $channel;

    /**
     * @param Channel $channel
     */
    public function __construct(Channel $channel)
    {
        $this->channel = $channel;
    }

    /**
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     */
    public function log($level, $message, array $context = [])
    {
        go(function () use ($level, $message, $context) {
            $this->channel->push([
                'level' => $level,
                'message' => $message,
                'context' => $context
            ]);
        });
    }
}
