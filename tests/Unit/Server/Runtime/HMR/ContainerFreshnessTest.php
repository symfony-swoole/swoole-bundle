<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\HMR;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\ContainerFreshness;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Reads the metadata Symfony writes beside a compiled container to say whether it still matches the
 * files it was built from.
 *
 * @see ContainerFreshness for why this is what decides between reloading workers and restarting
 */
#[CoversClass(ContainerFreshness::class)]
final class ContainerFreshnessTest extends TestCase
{
    private string $directory;

    private string $containerFile;

    private string $watchedFile;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/swoole-bundle-freshness-' . bin2hex(random_bytes(6));
        $this->containerFile = $this->directory . '/TestContainer.php';
        $this->watchedFile = $this->directory . '/config.php';

        (new Filesystem())->mkdir($this->directory);
        file_put_contents($this->watchedFile, '<?php // a config file the container was built from');
        file_put_contents($this->containerFile, '<?php // the compiled container');
        file_put_contents($this->containerFile . '.meta', serialize([new FileResource($this->watchedFile)]));
    }

    #[Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testThatAContainerNewerThanEverythingItWasBuiltFromIsFresh(): void
    {
        self::assertFalse($this->freshness()->isStale());
    }

    public function testThatAChangedResourceMakesItStale(): void
    {
        $this->touchWatchedFileAfterTheContainer();

        self::assertTrue($this->freshness()->isStale());
    }

    /**
     * The one that matters for a server, and the reason this class reads the metadata itself rather
     * than calling ConfigCache::isFresh().
     *
     * A server asks this every couple of seconds for as long as it is up, and the first answer is
     * always "fresh" - the container has just been built. Symfony's own checker memoizes that answer in
     * a private static array keyed by the resource and the container's timestamp, neither of which
     * changes while the server runs, so every later call in that process returns the first one and the
     * change is never seen. The assertion below is exactly that scenario.
     */
    public function testThatItStillSeesAChangeAfterAnswering(): void
    {
        $freshness = $this->freshness();

        self::assertFalse($freshness->isStale(), 'Nothing has changed yet.');

        $this->touchWatchedFileAfterTheContainer();

        self::assertTrue(
            $freshness->isStale(),
            'A second answer from the same instance has to reflect the change, not repeat the first.',
        );
    }

    /**
     * Guards the claim the test above is built on, so that a Symfony release which drops the memo does
     * not quietly leave this class doing unnecessary work - and so the reason for it stays on record.
     */
    public function testThatSymfonysOwnCacheIsWhyThisClassExists(): void
    {
        $configCache = new ConfigCache($this->containerFile, true);
        self::assertTrue($configCache->isFresh());

        $this->touchWatchedFileAfterTheContainer();

        self::assertTrue(
            (new ConfigCache($this->containerFile, true))->isFresh(),
            'SelfCheckingResourceChecker still memoizes across instances - keep reading the metadata '
            . 'directly. If this fails, ConfigCache became usable for polling.',
        );
    }

    public function testThatItSaysNothingWithoutMetadataToCompareAgainst(): void
    {
        unlink($this->containerFile . '.meta');

        $freshness = $this->freshness();

        self::assertFalse($freshness->canTell());
        self::assertFalse($freshness->isStale(), 'Silence rather than a restart nobody asked for.');
    }

    public function testThatItSaysNothingWithoutAContainer(): void
    {
        unlink($this->containerFile);

        self::assertFalse($this->freshness()->canTell());
        self::assertFalse($this->freshness()->isStale());
    }

    private function freshness(): ContainerFreshness
    {
        return new ContainerFreshness($this->containerFile);
    }

    /**
     * Puts the watched file a clear second ahead of the container, which is what a save does and what
     * the resources are asked about. Whole seconds, because that is the resolution mtimes are compared
     * at and a same-second edit is indistinguishable from no edit.
     */
    private function touchWatchedFileAfterTheContainer(): void
    {
        $containerMtime = filemtime($this->containerFile);
        self::assertIsInt($containerMtime);

        touch($this->watchedFile, $containerMtime + 1);
        clearstatcache();
    }
}
