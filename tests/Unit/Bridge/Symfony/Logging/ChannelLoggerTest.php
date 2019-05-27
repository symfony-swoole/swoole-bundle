<?php
/*
 * @author     mfris
 * @copyright  PIXELFEDERATION s.r.o.
 * @license    Internal use only
 */

namespace K911\Swoole\Tests\Unit\Bridge\Symfony\Logging;

use K911\Swoole\Bridge\Symfony\Logging\ChannelLogger;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LogLevel;
use Swoole\Coroutine\Channel;
use Swoole\Event;

/**
 *
 */
class ChannelLoggerTest extends TestCase
{
    /**
     * @return void
     */
    public function testChannelReceivesData(): void
    {
        $level = LogLevel::NOTICE;
        $message = 'test';
        $context = ['context' => true];
        $channelArgs = [
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];

        /* @var $channel ObjectProphecy|Channel */
        $channel = $this->prophesize(Channel::class);
        /* @var $pushMethod MethodProphecy */
        $pushMethod = $channel->push();
        $pushMethod->shouldBeCalled()->withArguments([$channelArgs]);
        /* @var $channelInstance Channel */
        $channelInstance = $channel->reveal();

        $logger = new ChannelLogger($channelInstance);
        $logger->log($level, $message, $context);
        Event::wait(); // just to be sure, because the channel receives data in a coroutine
    }
}
