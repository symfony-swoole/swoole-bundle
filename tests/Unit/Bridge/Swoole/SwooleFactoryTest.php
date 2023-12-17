<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Bridge\Swoole;

use K911\Swoole\Bridge\Swoole\Swoole;
use K911\Swoole\Bridge\Swoole\SwooleFactory;
use PHPUnit\Framework\TestCase;

class SwooleFactoryTest extends TestCase
{
    public function testNewInstanceCreation(): void
    {
        $factory = new SwooleFactory();
        $swoole = $factory->newInstance();

        $this->assertInstanceOf(Swoole::class, $swoole);
    }
}
