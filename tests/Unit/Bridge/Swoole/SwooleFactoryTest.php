<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Swoole;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Swoole\Swoole;
use SwooleBundle\SwooleBundle\Bridge\Swoole\SwooleFactory;

class SwooleFactoryTest extends TestCase
{
    public function testNewInstanceCreation(): void
    {
        $factory = new SwooleFactory();
        $swoole = $factory->newInstance();

        $this->assertInstanceOf(Swoole::class, $swoole);
    }
}
