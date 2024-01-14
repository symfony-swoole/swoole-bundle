<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Common\Adapter;

interface SwooleFactory
{
    public function newInstance(): Swoole;
}
