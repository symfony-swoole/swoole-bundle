<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\HMR;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\NonReloadableCodeFreshness;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Watches the files the master had loaded when it forked - the ones no reload can replace.
 *
 * The baseline is whatever get_included_files() returns when boot() runs, so these tests include a
 * file of their own first and then change it. Separate processes, because a file included by one test
 * stays included for every test after it in the same one.
 *
 * @see NonReloadableCodeFreshness
 */
#[CoversClass(NonReloadableCodeFreshness::class)]
#[RunTestsInSeparateProcesses]
final class NonReloadableCodeFreshnessTest extends TestCase
{
    private string $directory;

    private string $loadedFile;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/swoole-bundle-nonreloadable-' . bin2hex(random_bytes(6));
        $this->loadedFile = $this->directory . '/LoadedBeforeTheFork.php';

        (new Filesystem())->mkdir($this->directory);
        file_put_contents($this->loadedFile, "<?php\n// loaded before the workers forked\n");

        require $this->loadedFile;
    }

    #[Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testThatUntouchedCodeNeedsNoRestart(): void
    {
        self::assertNull($this->booted()->reasonForRestart());
    }

    public function testThatChangedCodeNeedsARestartAndSaysWhich(): void
    {
        $freshness = $this->booted();

        $this->rewriteLoadedFile("<?php\n// edited after the workers forked\n");

        $reason = $freshness->reasonForRestart();

        self::assertNotNull($reason);
        self::assertStringContainsString($this->loadedFile, $reason);
    }

    /**
     * A git checkout, an editor, a build step: all of them rewrite files byte for byte, and a warning
     * that fires for those gets ignored by the third time it is wrong.
     */
    public function testThatAContentIdenticalRewriteIsNotAChange(): void
    {
        $freshness = $this->booted();

        $this->rewriteLoadedFile((string) file_get_contents($this->loadedFile));

        self::assertNull($freshness->reasonForRestart());
    }

    public function testThatADeletedFileNeedsARestart(): void
    {
        $freshness = $this->booted();

        unlink($this->loadedFile);
        clearstatcache();

        self::assertNotNull($freshness->reasonForRestart());
    }

    /**
     * Vendor and the compiled cache are dropped: neither is edited in place while developing, and
     * carrying them would mean walking thousands of files every couple of seconds for nothing.
     */
    public function testThatVendorAndTheCacheDirectoryAreNotWatched(): void
    {
        $freshness = $this->booted();
        $watched = $freshness->watchedFileCount();

        self::assertGreaterThan(0, $watched, 'This test file itself is loaded, so something is watched.');
        self::assertLessThan(
            count(get_included_files()),
            $watched,
            'Vendor is the bulk of what a booted process has loaded and none of it should be here.',
        );
    }

    /**
     * Nothing is watched until the master says what it had loaded, and until then there is nothing to
     * report rather than everything.
     */
    public function testThatItSaysNothingBeforeItHasABaseline(): void
    {
        $freshness = new NonReloadableCodeFreshness($this->cacheDir());

        self::assertFalse($freshness->canTell());
        self::assertNull($freshness->reasonForRestart());
    }

    private function booted(): NonReloadableCodeFreshness
    {
        $freshness = new NonReloadableCodeFreshness($this->cacheDir());
        $freshness->boot();

        self::assertTrue($freshness->canTell());

        return $freshness;
    }

    /**
     * Somewhere no test file lives, so that nothing this test loaded is filtered out as "cache".
     */
    private function cacheDir(): string
    {
        return $this->directory . '/var/cache';
    }

    /**
     * Writes the file with an mtime a clear second on, which is what a save does and the gate the
     * content check sits behind.
     */
    private function rewriteLoadedFile(string $contents): void
    {
        $was = filemtime($this->loadedFile);
        self::assertIsInt($was);

        file_put_contents($this->loadedFile, $contents);
        touch($this->loadedFile, $was + 1);
        clearstatcache();
    }
}
