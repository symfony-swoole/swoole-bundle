<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Tests\Helper\TestToken;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Guards the one boot this bundle cannot leave to Symfony: several processes starting on a cold cache.
 *
 * Symfony locks its own dump, so of any number of processes exactly one builds the container and the rest
 * wait and load what it wrote. With coroutines on that is not the end of the boot, because this bundle then
 * rewrites what was dumped - every generated factory is replaced by a wrapper taking a mutex around first
 * instantiation, and z-engine strips `final` off the classes the proxies extend. Both halves are shared
 * state written by one process and read by all the others, and neither was covered by Symfony's lock:
 *
 * - a process that had already loaded the plain container met the wrappers when it instantiated a service,
 *   and died on the parent class its own `load()` never required: "Attempted to load class
 *   getKernelProxyService__Overridden from namespace ContainerM021pf6";
 * - a process that read the list of classes to unfinal before the compiling process had written it unfinalled
 *   nothing, then loaded that process's proxies: "Class SwooleBundleProxy\__PM__\... cannot extend final
 *   class ...".
 *
 * Eight processes reproduced both, eight times out of eight, and neither is visible with one process or a
 * warm cache - which is every other test in this suite.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Kernel\CoroutinesSupportingKernel::initializeContainer()
 */
final class ConcurrentContainerBuildTest extends TestCase
{
    /**
     * The environment the rest of the coroutine tests use, rather than one of this test's own, and for the
     * only reason that matters here: it has to be big enough to lose the race in. A minimal environment -
     * coroutines on, one proxified service - passes eight times out of eight on the unfixed bundle, because
     * the rewrite is over before the next process gets to the container. This one has the services, the
     * controllers, the message handlers and the compile processors of the whole fixture app, and fails eight
     * times out of eight.
     *
     * Its cache directory is emptied here, which the fixture app expects - feature tests wipe it between
     * tests - and the next test using this environment builds it again.
     */
    private const string ENV = 'coroutines';

    /**
     * The console rather than a bare kernel boot, and that is what makes this test reproduce anything: both
     * failures come from *using* the container - the wrappers are loaded when a service is instantiated, and
     * the proxies when one is proxified. A kernel that only boots touches neither, and passes either way.
     */
    private const string SCRIPT = __DIR__ . '/../Fixtures/Symfony/app/console';

    private const string CACHE_DIR = __DIR__ . '/../Fixtures/Symfony/app/var%s/cache/' . self::ENV;

    /**
     * Enough of them to lose the race reliably. The failures this covers came out of the processes that did
     * not build the container, so one waiting process would be a coin toss and eight is not.
     */
    private const int PROCESSES = 8;

    /**
     * One of them compiles the container from scratch while the rest wait for it, and that is the slow part.
     */
    private const float TIMEOUT_SECONDS = 180.0;

    public function testThatEveryProcessBootsWhenTheyAllStartOnAColdCache(): void
    {
        (new Filesystem())->remove(sprintf(self::CACHE_DIR, TestToken::suffix()));

        $boots = [];

        for ($i = 0; $i < self::PROCESSES; $i++) {
            $boot = new Process(
                [PHP_BINARY, (string) realpath(self::SCRIPT), 'about', '--env=' . self::ENV],
                null,
                ['APP_RUNTIME_MODE' => 'web=1&worker=1'],
                null,
                self::TIMEOUT_SECONDS * TestToken::timeoutFactor(),
            );

            // Started rather than run: they have to be inside the same boot at the same time, which is the
            // only condition under which any of this goes wrong.
            $boot->start();
            $boots[] = $boot;
        }

        foreach ($boots as $boot) {
            $boot->wait();
        }

        foreach ($boots as $index => $boot) {
            self::assertSame(
                0,
                $boot->getExitCode(),
                sprintf(
                    "process %d failed to boot.\nstdout: %s\nstderr: %s",
                    $index,
                    $boot->getOutput(),
                    $boot->getErrorOutput(),
                ),
            );

            self::assertStringContainsString(self::ENV, $boot->getOutput());
        }
    }
}
