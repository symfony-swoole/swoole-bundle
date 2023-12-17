<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Bridge\OpenSwoole;

use K911\Swoole\Bridge\OpenSwoole\OpenSwoole;
use K911\Swoole\Bridge\OpenSwoole\OpenSwooleFactory;
use PHPUnit\Framework\TestCase;

class OpenSwooleFactoryTest extends TestCase
{
    public function testNewInstanceCreation(): void
    {
        $factory = new OpenSwooleFactory();
        $swoole = $factory->newInstance();

        $this->assertInstanceOf(OpenSwoole::class, $swoole);
    }
}
