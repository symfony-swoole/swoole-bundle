<?php
/*
 * @author     mfris
 * @copyright  PIXELFEDERATION s.r.o.
 * @license    Internal use only
 */

namespace K911\Swoole\Tests\Unit\Bridge\Symfony\Logging;

use K911\Swoole\Bridge\Symfony\Logging\MasterLogger;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Swoole\Coroutine\Channel;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

/**
 *
 */
class MasterLoggerTest extends TestCase
{
    /**
     * @return void
     */
    public function testLogging():void
    {
        $level = LogLevel::NOTICE;
        $message = 'test';
        $context = ['context' => true];
        $channelArgs = [
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];

        /* @var $wrappedLogger ObjectProphecy|LoggerInterface */
        $wrappedLogger = $this->prophesize(LoggerInterface::class);
        /* @var $logMethod MethodProphecy */
        $logMethod = $wrappedLogger->log();
        $logMethod->shouldBeCalled()->withArguments([$level, $message, $context]);
        /* @var $wrappedLoggerInstance LoggerInterface */
        $wrappedLoggerInstance = $wrappedLogger->reveal();

        $channel = new Channel();

        $commandEvent = $this->prophesize(ConsoleCommandEvent::class);
        /* @var $commandEventInstance ConsoleCommandEvent */
        $commandEventInstance = $commandEvent->reveal();

        $logger = new MasterLogger($channel, $wrappedLoggerInstance);
        $logger->onInitialize($commandEventInstance);
        $channel->push($channelArgs);
        usleep(200000);

        $logger->onTerminate();
        $channel->close();
    }

    /**
     * @return void
     */
    public function testChannelManipulation():void
    {
        $waiting = new class {
            public $isWaiting = true;
        };
        $controlChannel = new Channel();

        /* @var $wrappedLogger ObjectProphecy|LoggerInterface */
        $wrappedLogger = $this->prophesize(LoggerInterface::class);
        /* @var $wrappedLoggerInstance LoggerInterface */
        $wrappedLoggerInstance = $wrappedLogger->reveal();

        /* @var $channel ObjectProphecy|Channel */
        $channel = $this->prophesize(Channel::class);
        /* @var $popMethod MethodProphecy */
        $popMethod = $channel->pop();
        $popMethod->shouldBeCalled();
        /* @var $closeMethod MethodProphecy */
        $closeMethod = $channel->close();
        $closeMethod->shouldBeCalledOnce();

        /* @var $channelInstance Channel */
        $channelInstance = $channel->reveal();
        $commandEvent = $this->prophesize(ConsoleCommandEvent::class);
        /* @var $commandEventInstance ConsoleCommandEvent */
        $commandEventInstance = $commandEvent->reveal();

        go(function() use (
            $wrappedLoggerInstance,
            $channelInstance,
            $popMethod,
            $closeMethod,
            $commandEventInstance,
            $controlChannel,
            $waiting
        ) {
            $popMethod->will(function () use ($controlChannel) {
                return $controlChannel->pop();
            });

            $closeMethod->will(function () use ($controlChannel) {
                $controlChannel->close();
            });

            $logger = new MasterLogger($channelInstance, $wrappedLoggerInstance);
            $logger->onInitialize($commandEventInstance);
            $logger->onTerminate();
            $waiting->isWaiting = false;
        });

        while ($waiting->isWaiting) {
            usleep(1000);
        }
    }

    /**
     * @return void
     */
    public function testChannelLoggingProcess():void
    {
        $waiting = new class {
            public $isWaiting = true;
        };
        $controlChannel = new Channel(1024);

        $level = LogLevel::NOTICE;
        $message = 'test';
        $context = ['context' => true];
        $channelArgs = [
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];

        $controlChannel->push($channelArgs);

        $open = new class {
            public $open = true;
        };

        /* @var $wrappedLogger ObjectProphecy|LoggerInterface */
        $wrappedLogger = $this->prophesize(LoggerInterface::class);
        /* @var $logMethod MethodProphecy */
        $logMethod = $wrappedLogger->log();
        $logMethod->shouldBeCalled()->withArguments([$level, $message, $context]);
        /* @var $wrappedLoggerInstance LoggerInterface */
        $wrappedLoggerInstance = $wrappedLogger->reveal();

        /* @var $channel ObjectProphecy|Channel */
        $channel = $this->prophesize(Channel::class);
        /* @var $popMethod MethodProphecy */
        $popMethod = $channel->pop();

        /* @var $closeMethod MethodProphecy */
        $closeMethod = $channel->close();
        $closeMethod->shouldBeCalledOnce();
        /* @var $channelInstance Channel */
        $channelInstance = $channel->reveal();

        $commandEvent = $this->prophesize(ConsoleCommandEvent::class);
        /* @var $commandEventInstance ConsoleCommandEvent */
        $commandEventInstance = $commandEvent->reveal();

        go(function() use (
            $open,
            $popMethod,
            $closeMethod,
            $channelInstance,
            $wrappedLoggerInstance,
            $commandEventInstance,
            $controlChannel,
            $waiting
        ) {
            $popMethod->shouldBeCalled()->will(function () use ($open, $controlChannel) {
                if ($open->open) {
                    $open->open = false;

                    return $controlChannel->pop();
                }

                return $controlChannel->pop();
            });

            $closeMethod->will(function () use ($controlChannel) {
                $controlChannel->close();
            });

            $logger = new MasterLogger($channelInstance, $wrappedLoggerInstance);
            $logger->onInitialize($commandEventInstance);
            $logger->onTerminate();
            $waiting->isWaiting = false;
        });

        while ($waiting->isWaiting) {
            usleep(1000);
        }
    }
}
