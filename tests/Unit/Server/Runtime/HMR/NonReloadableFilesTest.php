<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\HMR;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\NonReloadableFiles;
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
