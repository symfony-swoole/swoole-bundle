<?php

declare(strict_types=1);

namespace K911\Swoole\Common\Adapter;

interface SwooleFactory
{
    public function newInstance(): Swoole;
}
