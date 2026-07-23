<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation\Session;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorage;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorageFactory;
use SwooleBundle\SwooleBundle\Server\Session\Storage;
use Symfony\Component\HttpFoundation\Request;

final class SwooleSessionStorageFactoryTest extends TestCase
{
    private Storage&MockObject $storage;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(Storage::class);
    }

    public function testCreateStorageCreatesSwooleSessionStorageInInitialState(): void
    {
        $subject = new SwooleSessionStorageFactory($this->storage);

        $result = $subject->createStorage(new Request());

        $this->assertInstanceOf(SwooleSessionStorage::class, $result);
        $this->assertFalse($result->isStarted());
        $this->assertSame('', $result->getId());
    }
}
