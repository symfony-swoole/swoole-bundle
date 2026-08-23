<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime\HMR;

use Override;
use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;
use Throwable;

/**
 * Whether the compiled container still matches the files it was built from.
 *
 * This is the question HMR cannot answer for itself, and the one that decides which kind of reload a
 * change needs. A worker reload re-forks the workers from the master's memory image: it can pick up a
 * class the worker had not loaded yet, and it can pick up nothing else. The kernel is booted in the
 * master before Server::start(), so a reloaded worker never calls Kernel::boot() again, never reaches
 * the freshness check Symfony would do there, and never rebuilds anything - emptying the cache
 * directory under a running server changes nothing at all, because no process re-reads it.
 *
 * Which means a container that has gone stale can only be applied by a new process, and the useful
 * thing to know is whether it has. Symfony already tracks that exactly: the dumped container is written
 * through a ConfigCache, with a .meta file beside it listing every resource it was built from - config
 * files, bundle files, env vars, and whatever else the compiler passes registered. Reading that is a
 * great deal more accurate than watching paths and guessing which of them the container cared about.
 *
 * ## Why the resources are read here rather than through ConfigCache
 *
 * ConfigCache::isFresh() answers this question in one call and is the obvious thing to reach for. It
 * cannot be used from a server: SelfCheckingResourceChecker, the checker it builds in debug, memoizes
 * every answer in a private static array keyed by the resource and the cache's timestamp. Neither part
 * of that key changes while the server runs, so the first call - made while everything was still fresh
 * - is the answer every later call gets, for the life of the process. The assumption is that a checker
 * runs once per process, during boot, which is true of every Symfony application except one that
 * stays up.
 *
 * Reading the metadata directly avoids the memo, and costs less anyway: the resources are unserialized
 * once and asked again on each poll, rather than a fresh ConfigCache unserializing the file every time.
 *
 * ## What it does not see from inside a worker
 *
 * The metadata's ReflectionClassResource entries - 164 of the 234 in this bundle's fixture - decide
 * freshness by reflecting the class and hashing the result, against the definition loaded in the
 * process asking. A worker holds the definition it forked with, so those entries report fresh whatever
 * the file now says. What is left, and what this reliably catches, are the config files and globs
 * compared by mtime. NonReloadableCodeFreshness covers the classes.
 *
 * Silent when it cannot tell, deliberately. A container compiled with debug off has no .meta beside it
 * at all, and there is nothing to be gained by reporting every such server permanently stale.
 */
final class ContainerFreshness implements RestartCondition
{
    /**
     * @var list<SelfCheckingResourceInterface>|null the resources the container was built from, read
     *                                               once and re-asked on every poll
     */
    private ?array $resources = null;

    /**
     * The container mtime the resources were read for, and what they are asked to be no newer than.
     * A change to it means a new container, whose metadata has to be read afresh.
     */
    private int $builtAt = 0;

    public function __construct(private readonly string $containerFile) {}

    #[Override]
    public function reasonForRestart(): ?string
    {
        if (!$this->isStale()) {
            return null;
        }

        return 'the compiled container no longer matches the files it was built from, and the kernel is '
            . 'booted before the workers fork, so no reloaded worker ever compiles a new one';
    }

    public function isStale(): bool
    {
        // Asked from a worker that has been up for hours, and every answer below comes from a
        // filemtime(). PHP caches those per process, so without this the check keeps reporting the
        // mtimes the worker happened to see the first time it looked and never notices a thing.
        clearstatcache();

        $resources = $this->resources();

        if ($resources === []) {
            return false;
        }

        foreach ($resources as $resource) {
            if ($resource->isFresh($this->builtAt)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Whether there is a compiled container, and a record of what it was built from to compare it with.
     */
    public function canTell(): bool
    {
        clearstatcache();

        return $this->resources() !== [];
    }

    /**
     * @return list<SelfCheckingResourceInterface>
     */
    private function resources(): array
    {
        $builtAt = @filemtime($this->containerFile);

        if ($builtAt === false) {
            return $this->forget();
        }

        if ($this->resources !== null && $this->builtAt === $builtAt) {
            return $this->resources;
        }

        $meta = @file_get_contents($this->containerFile . '.meta');

        if ($meta === false) {
            return $this->forget();
        }

        try {
            $unserialized = unserialize($meta, ['allowed_classes' => true]);
        } catch (Throwable) {
            return $this->forget();
        }

        if (!is_array($unserialized)) {
            return $this->forget();
        }

        $this->builtAt = $builtAt;

        // Anything that cannot check itself - a resource type whose checker lives elsewhere - is
        // dropped rather than guessed at. What is left still covers the config files, the bundle files
        // and the classes the compiler read, which is what a developer changes.
        return $this->resources = array_values(array_filter(
            $unserialized,
            static fn(mixed $resource): bool => $resource instanceof SelfCheckingResourceInterface,
        ));
    }

    /**
     * @return array{}
     */
    private function forget(): array
    {
        $this->resources = null;
        $this->builtAt = 0;

        return [];
    }
}
