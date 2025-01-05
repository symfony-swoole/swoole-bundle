<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Common\Adapter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\CommonSwoole\SystemSwooleFactory;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Common\Adapter\SwooleFactory;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Common\System\System;

final class SystemSwooleFactoryTest extends TestCase
{
    #[DataProvider('extensions')]
    public function testNewInstanceCreation(string $extension): void
    {
        if (!extension_loaded($extension)) {
            self::markTestSkipped(sprintf('Extension %s is not loaded.', $extension));
        }

        $swooleFactory = $this->createMock(SwooleFactory::class);
        $openSwooleFactory = $this->createMock(SwooleFactory::class);

        $expectingFactory = $extension === Extension::SWOOLE ? $swooleFactory : $openSwooleFactory;
        $expectingFactory->expects($this->once())
            ->method('newInstance')
            ->willReturn($this->createMock(Swoole::class));

        $factory = new SystemSwooleFactory(
            System::create(),
            [
                Extension::SWOOLE => $swooleFactory,
                Extension::OPENSWOOLE => $openSwooleFactory,
            ],
        );

        $factory->newInstance();
    }

    public function testNewInstanceCreationFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Adapter factory for extension "(swoole|openswoole)" not found\./');

        $factory = new SystemSwooleFactory(
            System::create(),
            [],
        );

        $factory->newInstance();
    }

    /**
     * @return array<array{0: string}>
     */
    public static function extensions(): array
    {
        return [
            [Extension::SWOOLE],
            [Extension::OPENSWOOLE],
        ];
    }
}
