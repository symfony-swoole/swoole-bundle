<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server\Runtime\HMR;

use K911\Swoole\Server\Runtime\HMR\NonReloadableFiles;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class NonReloadableFilesTest extends TestCase
{
    public function testBootDumpFiles(): void
    {
        $fsmock = $this->createMock(Filesystem::class);
        $fsmock->expects($this->exactly(2))->method('dumpFile')->withAnyParameters();
        $nrf = new NonReloadableFiles('cache', '/var/www', $fsmock);
        $nrf->boot();
    }
}
