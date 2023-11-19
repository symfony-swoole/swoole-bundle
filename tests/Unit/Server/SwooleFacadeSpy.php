<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server;

use K911\Swoole\Common\SwooleFacade;

final class SwooleFacadeSpy implements SwooleFacade
{
    public $registeredTick = false;

    public $registeredTickTuple = [];

    public function tick(int $intervalMs, callable $callbackFunction, ...$params): int|bool
    {
        $this->registeredTick = true;
        $this->registeredTickTuple = [$intervalMs, $callbackFunction];

        return true;
    }
}
