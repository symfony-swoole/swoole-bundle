<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Tests\Helper\TestToken;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Guards a boot from a cache directory that holds a compiled container but not everything that was written
 * beside it.
 *
 * With coroutines on, the compile strips `final` from every class the service pool proxies, in memory, and every
 * later process has to strip the same classes again before it instantiates anything. The list of them used to be
 * a cache of its own beside the container (swoole_bundle/modification), and the two could be lost apart: a cache
 * directory deleted while another process was compiling into it - `swoole:server:watch` clears it when a config
 * file changes, and a console command in a container sharing the same bind mount was compiling - kept the
 * container and lost the list. Every boot after that loaded the container, stripped nothing, and died on the
 * first proxy of a final class:
 *
 *     Provided class "App\Kernel" is final and cannot be proxied
 *
 * or, when the proxy files had survived:
 *
 *     Class SwooleBundleProxy\__PM__\...\Generated... cannot extend final class App\Kernel
 *
 * and nothing short of deleting the cache by hand brought the server back. The list is in the container now, so
 * what can still be lost beside it is what swoole_bundle/ holds - the generated proxies above all, which are
 * generated again when first asked for.
 *
 * The fixture kernel is final, like most application kernels, and the kernel proxy exists whenever coroutines
 * are on - which is what makes an `about` enough to reproduce it.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Kernel\CoroutinesSupportingKernel::initializeContainer()
 */
final class ContainerBootFromPartialCacheTest extends TestCase
{
    private const string ENV = 'coroutines';

    private const string SCRIPT = __DIR__ . '/../Fixtures/Symfony/app/console';

    private const string CACHE_DIR = __DIR__ . '/../Fixtures/Symfony/app/var%s/cache/' . self::ENV;

    private const float TIMEOUT_SECONDS = 180.0;

    protected function tearDown(): void
    {
        // A failing run leaves exactly the cache this test is about, and the next test in this worker to use the
        // environment would boot from it. Rebuilt from scratch instead.
        (new Filesystem())->remove($this->cacheDir());

        parent::tearDown();
    }

    /**
     * @param list<string> $lostPaths relative to the environment's cache directory
     */
    #[DataProvider('provideLostParts')]
    public function testThatTheKernelBootsFromACacheThatLostWhatWasWrittenBesideTheContainer(array $lostPaths): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->cacheDir());

        $this->assertBoots('the first boot, which compiles the container');

        foreach ($lostPaths as $lostPath) {
            $path = $this->cacheDir() . '/' . $lostPath;
            self::assertDirectoryExists(
                $path,
                sprintf('the compile did not write %s, so this test tests nothing', $lostPath),
            );
            $filesystem->remove($path);
        }

        $this->assertBoots('the boot from the partial cache');
        $this->assertBoots('the boot after that');
    }

    /**
     * The other side of the check: a cache that lost nothing must not look as if it had. A container rebuilt on
     * every boot would still boot, and every test above would still pass - it would just cost a full compile
     * each time, which is how a check like this goes wrong without anyone noticing.
     */
    public function testThatABootFromACompleteCacheKeepsTheContainer(): void
    {
        (new Filesystem())->remove($this->cacheDir());

        $this->assertBoots('the first boot, which compiles the container');

        $containers = glob($this->cacheDir() . '/*Container.php');
        self::assertIsArray($containers);
        self::assertCount(1, $containers);
        $container = $containers[0];
        $compiled = [filemtime($container), fileinode($container)];

        $this->assertBoots('the second boot');

        clearstatcache();
        self::assertSame($compiled, [filemtime($container), fileinode($container)], 'the container was rebuilt');
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function provideLostParts(): iterable
    {
        yield 'the generated proxies' => [
            ['swoole_bundle/services'],
        ];

        yield 'everything the bundle writes beside the container' => [
            ['swoole_bundle'],
        ];
    }

    private function assertBoots(string $which): void
    {
        $boot = new Process(
            [PHP_BINARY, (string) realpath(self::SCRIPT), 'about', '--env=' . self::ENV],
            null,
            ['APP_RUNTIME_MODE' => 'web=1&worker=1'],
            null,
            self::TIMEOUT_SECONDS * TestToken::timeoutFactor(),
        );
        $boot->run();

        self::assertSame(
            0,
            $boot->getExitCode(),
            sprintf(
                "%s failed.\nstdout: %s\nstderr: %s",
                $which,
                $boot->getOutput(),
                $boot->getErrorOutput(),
            ),
        );
        self::assertStringContainsString(self::ENV, $boot->getOutput());
    }

    private function cacheDir(): string
    {
        return sprintf(self::CACHE_DIR, TestToken::suffix());
    }
}
