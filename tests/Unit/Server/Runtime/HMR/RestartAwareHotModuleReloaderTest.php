<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\HMR;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\ContainerFreshness;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\RestartAwareHotModuleReloader;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\RestartCondition;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The half of HMR that knows a reload will not help.
 *
 * @see RestartAwareHotModuleReloader
 */
#[CoversClass(RestartAwareHotModuleReloader::class)]
#[RunTestsInSeparateProcesses]
final class RestartAwareHotModuleReloaderTest extends TestCase
{
    private string $directory;

    private string $containerFile;

    private string $watchedFile;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/swoole-bundle-restart-aware-' . bin2hex(random_bytes(6));
        $this->containerFile = $this->directory . '/TestContainer.php';
        $this->watchedFile = $this->directory . '/config.php';

        (new Filesystem())->mkdir($this->directory);
        file_put_contents($this->watchedFile, '<?php');
        file_put_contents($this->containerFile, '<?php');
        file_put_contents($this->containerFile . '.meta', serialize([new FileResource($this->watchedFile)]));
    }

    #[Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testThatAFreshContainerLeavesTheReloaderToItsWork(): void
    {
        $inner = new HMRSpy();
        $logger = self::newLogger();

        $this->reloader($inner, $logger)->tick(ReloadCountingServerMock::make());

        self::assertTrue($inner->ticked());
        self::assertSame([], $logger->warnings());
    }

    /**
     * Reloading around a stale container would drop every connection the workers hold and still leave
     * the old container in place, so the reloader underneath is not reached at all.
     */
    public function testThatAStaleContainerStopsTheReloaderAndIsReported(): void
    {
        $this->changeTheWatchedFile();

        $inner = new HMRSpy();
        $logger = self::newLogger();

        $this->reloader($inner, $logger)->tick(ReloadCountingServerMock::make());

        self::assertFalse($inner->ticked(), 'A reload cannot apply this change, so it is not attempted.');
        self::assertCount(1, $logger->warnings());
        self::assertStringContainsString('has to be restarted', $logger->warnings()[0]);
    }

    /**
     * The tick runs every couple of seconds and the container stays stale until somebody restarts the
     * server, so saying it every time would bury the first one.
     */
    public function testThatItIsReportedOncePerGenerationOfStaleness(): void
    {
        $this->changeTheWatchedFile();

        $logger = self::newLogger();
        $reloader = $this->reloader(new HMRSpy(), $logger);

        $reloader->tick(ReloadCountingServerMock::make());
        $reloader->tick(ReloadCountingServerMock::make());
        $reloader->tick(ReloadCountingServerMock::make());

        self::assertCount(1, $logger->warnings());
    }

    /**
     * The conditions are asked as a set, so a reason from any of them pauses the reloader - the caller
     * wants one answer to "will a reload do?", not a checklist.
     */
    public function testThatAnyConditionCanPauseIt(): void
    {
        $inner = new HMRSpy();
        $logger = self::newLogger();

        $reloader = new RestartAwareHotModuleReloader(
            $inner,
            [
                new ContainerFreshness($this->containerFile),
                new class implements RestartCondition {
                    #[Override]
                    public function reasonForRestart(): string
                    {
                        return 'something else entirely has changed';
                    }
                },
            ],
            $logger,
        );

        $reloader->tick(ReloadCountingServerMock::make());

        self::assertFalse($inner->ticked());
        self::assertStringContainsString('something else entirely has changed', $logger->warnings()[0]);
    }

    private function reloader(HMRSpy $inner, LoggerSpy $logger): RestartAwareHotModuleReloader
    {
        return new RestartAwareHotModuleReloader(
            $inner,
            [new ContainerFreshness($this->containerFile)],
            $logger,
        );
    }

    private function changeTheWatchedFile(): void
    {
        $containerMtime = filemtime($this->containerFile);
        self::assertIsInt($containerMtime);

        touch($this->watchedFile, $containerMtime + 1);
        clearstatcache();
    }

    private static function newLogger(): LoggerSpy
    {
        return new LoggerSpy();
    }
}
