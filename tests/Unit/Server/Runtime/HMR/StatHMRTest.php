<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\HMR;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\StatHMR;

#[RunTestsInSeparateProcesses]
final class StatHMRTest extends TestCase
{
    private string $dir;

    private string $watched;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/stathmr_' . uniqid('', true);
        mkdir($this->dir, 0o777, true);
        $this->watched = $this->dir . '/Watched.php';
        file_put_contents($this->watched, "<?php\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->watched);
        @rmdir($this->dir);
    }

    public function testDoesNotReloadWithoutChanges(): void
    {
        $server = ReloadCountingServerMock::make();
        $hmr = new StatHMR('/nonexistent-cache', [], fn(): array => [$this->watched]);
        $hmr->boot();

        $hmr->tick($server); // registers the watch with current mtime
        $hmr->tick($server); // nothing changed since registration

        self::assertSame(0, $server->reloadCount());
    }

    public function testReloadsWhenWatchedFileChanges(): void
    {
        $server = ReloadCountingServerMock::make();
        $hmr = new StatHMR('/nonexistent-cache', [], fn(): array => [$this->watched]);
        $hmr->boot();

        $hmr->tick($server); // register watch
        self::assertSame(0, $server->reloadCount());

        touch($this->watched, time() + 10); // deterministically newer mtime
        $hmr->tick($server);

        self::assertSame(1, $server->reloadCount());
    }

    public function testIgnoresCacheFiles(): void
    {
        $cacheDir = $this->dir . '/cache';
        mkdir($cacheDir);
        $cacheFile = $cacheDir . '/Container.php';
        file_put_contents($cacheFile, "<?php\n");

        $server = ReloadCountingServerMock::make();
        $hmr = new StatHMR($cacheDir, [], static fn(): array => [$cacheFile]);
        $hmr->boot();

        $hmr->tick($server);
        touch($cacheFile, time() + 10);
        $hmr->tick($server);

        self::assertSame(0, $server->reloadCount());

        @unlink($cacheFile);
        @rmdir($cacheDir);
    }

    public function testReloadsWhenVendorFileChanges(): void
    {
        $vendorDir = $this->dir . '/vendor';
        mkdir($vendorDir);
        $vendorFile = $vendorDir . '/Dep.php';
        file_put_contents($vendorFile, "<?php\n");

        $server = ReloadCountingServerMock::make();
        $hmr = new StatHMR('/nonexistent-cache', [], static fn(): array => [$vendorFile]);
        $hmr->boot();

        $hmr->tick($server);
        touch($vendorFile, time() + 10);
        $hmr->tick($server);

        self::assertSame(1, $server->reloadCount());

        @unlink($vendorFile);
        @rmdir($vendorDir);
    }
}
