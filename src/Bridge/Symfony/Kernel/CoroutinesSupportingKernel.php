<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Kernel;

use SwooleBundle\SwooleBundle\Bridge\CommonSwoole\SystemSwooleFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\BlockingContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Modifier\Modifier;
use SwooleBundle\SwooleBundle\Reflection\ClassModifier;

/**
 * @phpstan-ignore trait.unused
 */
trait CoroutinesSupportingKernel
{
    /**
     * for the coroutines to work properly, the kernel __clone method has to be overriden,
     * otherwise the container wouldn't be shared between requests.
     */
    public function __clone()
    {
        // cloned kernel should have a fresh container and other state
    }

    /**
     * @return array<string>
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ($this->container->has('kernel_original')) {
            return [];
        }

        $this->container->set('kernel_original', $this);

        return [];
    }

    /**
     * this overrides the container class to a container, which is able to block the first instatiation
     * of requested service instance (because class autoloading is IO operation, which switches coroutine context).
     * the blocking ensures that only one service instance will be created concurrently and it will be registered
     * correctly in the container.
     */
    protected function getContainerBaseClass(): string
    {
        return BlockingContainer::class;
    }

    /**
     * this initializes logic which removes the final flag from proxified classes (if they are final).
     */
    protected function initializeContainer(): void
    {
        $cacheDir = $this->getCacheDir();
        $swooleFactory = SystemSwooleFactory::newFactoryInstance();
        BlockingContainer::initializeMutex($swooleFactory->newInstance());

        $this->initializeAndModifyContainerExclusively($cacheDir);

        if (!$this->areCoroutinesEnabled()) {
            return;
        }

        $this->container->set('kernel_original', $this);
        $this->container->set('kernel', $this->container->get('kernel_proxy'));
    }

    /**
     * Loading the container and rewriting it, as one step no other process may be inside.
     *
     * Symfony locks its own dump, so two processes never write one container. What it does not cover is what
     * this bundle does afterwards: with coroutines on, every generated factory of that container is replaced
     * by a wrapper that takes a mutex around first instantiation, and the wrapper extends an `_Overridden`
     * copy of the file it replaced. A process that has already loaded the plain container - it loaded it a
     * moment ago, in parent::initializeContainer() - then meets those wrappers when it instantiates a
     * service, and its own `load()` knows nothing about requiring their parents:
     *
     *     Attempted to load class "getKernelProxyService__Overridden" from namespace "ContainerM021pf6"
     *
     * Eight processes booting one cold cache reproduce it eight times out of eight. The lock covers the load
     * as well as the rewrite, so a process either builds and rewrites the container itself or waits and
     * loads one that is already wrapped, and never a half-rewritten one.
     *
     * A file of this bundle's own rather than the `.php.lock` Symfony uses, and deliberately: flock is held
     * per open file description, so taking Symfony's lock around a call that opens and locks the same path
     * again would have the process wait for itself.
     *
     * A lock that cannot be taken is not a reason to refuse to boot - the work goes ahead unlocked, the way
     * it always did.
     */
    private function initializeAndModifyContainerExclusively(string $cacheDir): void
    {
        if (!$this->containerNeedsExclusiveInitialization()) {
            $this->initializeAndModifyContainer($cacheDir);

            return;
        }

        $lockPath = $this->containerLockPath();
        $lockDir = dirname($lockPath);

        // The directory the lock lives in is the build directory, which on a cold cache does not exist yet -
        // Symfony creates it inside the call this lock is here to serialize. Without this, every process
        // fails to open the lock on exactly the run where it is needed, and quietly proceeds without one.
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0o777, true);
        }

        $lock = @fopen($lockPath, 'c+');

        if ($lock === false) {
            $this->initializeAndModifyContainer($cacheDir);

            return;
        }

        try {
            @flock($lock, LOCK_EX);

            $this->initializeAndModifyContainer($cacheDir);
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function initializeAndModifyContainer(string $cacheDir): void
    {
        // Before the container is compiled, if this boot compiles it: the compile strips `final` from what it
        // proxies, through z-engine, and records the classes in the container it builds.
        ClassModifier::initialize();

        parent::initializeContainer();

        if (!$this->areCoroutinesEnabled()) {
            return;
        }

        $this->removeFinalFlagsFromProxiedClasses();

        Modifier::modifyContainer($this->container, $cacheDir, $this->isDebug());

        if (is_file($this->coroutinesMarkerPath())) {
            return;
        }

        @touch($this->coroutinesMarkerPath());
    }

    /**
     * Strips `final` from the classes the loaded container's proxies extend, before anything is instantiated from
     * it - the first thing is the kernel proxy, straight after this boot.
     *
     * The list is in the container, and nowhere else. It used to be a cache of its own beside the container
     * (swoole_bundle/modification), read before the container was loaded - and the two could be lost apart. A
     * cache directory deleted while another process was compiling into it (swoole:server:watch clears it on a
     * config change, while a console command sharing the bind mount was compiling) kept the container, written
     * after the delete, and lost the list, written before. Symfony found the container fresh, so no boot rebuilt
     * it, and every boot from then on died on the first final class it proxied:
     *
     *     Provided class "App\Kernel" is final and cannot be proxied
     *
     * A list that is part of the container is there whenever the container is. In the process that compiled it
     * the classes are already stripped, and doing it again changes nothing.
     */
    private function removeFinalFlagsFromProxiedClasses(): void
    {
        if (!$this->container->hasParameter(ContainerConstants::PARAM_COROUTINES_FINAL_CLASSES)) {
            return;
        }

        /** @var list<class-string> $finalClasses */
        $finalClasses = $this->container->getParameter(ContainerConstants::PARAM_COROUTINES_FINAL_CLASSES);
        ClassModifier::removeFinalFlagsFromClasses($finalClasses);
    }

    /**
     * Whether this boot is one the lock is for, decided before the container is loaded - which is the only
     * moment it can be decided at, and the reason it is decided from disk.
     *
     * A kernel with coroutines off never has its container rewritten, so nothing about its boot needs
     * excluding anything, and a lock around it would serialize every boot of every process for nothing -
     * a test suite booting kernels in a dozen processes pays that on every one of them. But whether
     * coroutines are on is a container parameter, and reading it means having loaded the container this
     * question is about.
     *
     * So it is answered from two files instead:
     *
     * - no container at all, so this boot is going to build one. Whichever kind of kernel this is, if
     *   anything is going to be written, it is written now.
     * - a container, and beside it the marker a previous boot of this same kernel left when it rewrote
     *   one. That is the kernel whose boots this lock exists for.
     *
     * A kernel with coroutines off answers no to both from its second boot onwards, and is back to
     * exactly the boot it had before this lock existed.
     */
    private function containerNeedsExclusiveInitialization(): bool
    {
        if (!is_file($this->containerFilePath())) {
            return true;
        }

        return is_file($this->coroutinesMarkerPath());
    }

    private function containerFilePath(): string
    {
        return $this->getBuildDir() . DIRECTORY_SEPARATOR . $this->getContainerClass() . '.php';
    }

    /**
     * Left by a boot that rewrote the container, and read by the next one to know that it should take the
     * lock. Goes with the cache directory it describes: a cache:clear takes it away, and the cold boot that
     * follows locks anyway.
     */
    private function coroutinesMarkerPath(): string
    {
        return $this->containerFilePath() . '.swoole-coroutines';
    }

    /**
     * Beside the container, and named after it, so that one cache directory holding containers of several
     * kernels or environments has a lock per container rather than one for all of them.
     *
     * Rebuilt from the build directory and the container class rather than read off the kernel, because the
     * directory a warmup reboots into is private to Symfony's kernel. The two disagree only during a cache
     * warmup, which is one process, and has nothing to exclude.
     */
    private function containerLockPath(): string
    {
        return $this->containerFilePath() . '.swoole.lock';
    }

    private function areCoroutinesEnabled(): bool
    {
        if (!$this->container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return false;
        }

        return (bool) $this->container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED);
    }
}
