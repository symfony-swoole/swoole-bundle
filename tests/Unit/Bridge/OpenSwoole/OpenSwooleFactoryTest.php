<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\OpenSwoole;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\OpenSwoole;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\OpenSwooleFactory;

class OpenSwooleFactoryTest extends TestCase
{
    public function testNewInstanceCreation(): void
    {
        $factory = new OpenSwooleFactory();
        $swoole = $factory->newInstance();

        $this->assertInstanceOf(OpenSwoole::class, $swoole);
    }
}
