<?php

declare(strict_types=1);

namespace K911\Swoole\Coroutine;

use Assert\Assertion;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

final class PoolExecutor
{
    private $coroutines;
    private $coroutinesCount;
    private $resultsChannel;
    private $results = [];
    private $ran = false;
    private $finished = false;

    public function __construct(callable ...$coroutines)
    {
        $this->coroutines = $coroutines;
        $this->coroutinesCount = \count($coroutines);
        $this->resultsChannel = new Channel($this->coroutinesCount);
    }

    /**
     * Blocks until all coroutines have been finished.
     */
    public function run(): void
    {
        Assertion::false($this->ran, 'PoolExecutor cannot be run twice.');
        $this->ran = true;

        foreach ($this->coroutines as $coroutine) {
            \go($this->wrapDeferChannelPush($this->resultsChannel, $coroutine));
        }

        if (-1 !== Coroutine::getuid()) {
            $this->writeResults();
        } else {
            \go([$this, 'writeResults']);
            \swoole_event_wait();
        }

        $this->finished = true;
    }

    public function results(): array
    {
        Assertion::true($this->finished, 'PoolExecutor has not completed execution yet.');

        return $this->results;
    }

    private function writeResults(): void
    {
        $count = $this->coroutinesCount;
        while ($count > 0) {
            $this->results[] = $this->resultsChannel->pop();
            --$count;
        }
    }

    private function wrapDeferChannelPush(Channel $channel, callable $coroutine): callable
    {
        return function () use ($coroutine, $channel): void {
            $result = true;
            \defer(function () use ($channel, &$result): void {
                $channel->push($result);
            });
            $result = $coroutine() ?? true;
        };
    }
}
