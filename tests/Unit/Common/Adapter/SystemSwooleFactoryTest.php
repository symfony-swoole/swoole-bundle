<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Common\Adapter;

use K911\Swoole\Common\Adapter\Swoole;
use K911\Swoole\Common\Adapter\SwooleFactory;
use K911\Swoole\Common\Adapter\SystemSwooleFactory;
use K911\Swoole\Common\System\Extension;
use K911\Swoole\Common\System\System;
use PHPUnit\Framework\TestCase;

class SystemSwooleFactoryTest extends TestCase
{
    /**
     * @dataProvider extensions
     */
    public function testNewInstanceCreation(string $extension): void
    {
        if (!\extension_loaded($extension)) {
            self::markTestSkipped(\sprintf('Extension %s is not loaded.', $extension));
        }

        $swooleFactory = $this->createMock(SwooleFactory::class);
        $openSwooleFactory = $this->createMock(SwooleFactory::class);

        $expectingFactory = Extension::SWOOLE === $extension ? $swooleFactory : $openSwooleFactory;
        $expectingFactory->expects($this->once())
            ->method('newInstance')
            ->willReturn($this->createMock(Swoole::class))
        ;

        $factory = new SystemSwooleFactory(
            System::create(),
            new \ArrayIterator([
                Extension::SWOOLE => $swooleFactory,
                Extension::OPENSWOOLE => $openSwooleFactory,
            ])
        );

        $factory->newInstance();
    }

    public function testNewInstanceCreationFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Adapter factory for extension "(swoole|openswoole)" not found\./');

        $factory = new SystemSwooleFactory(
            System::create(),
            new \ArrayIterator([])
        );

        $factory->newInstance();
    }

    public static function extensions(): array
    {
        return [
            [Extension::SWOOLE],
            [Extension::OPENSWOOLE],
        ];
    }
}
