<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime\HMR;

use Override;
use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;

/**
 * Whether any of the code the workers cannot reload has changed since the server started.
 *
 * The files the master had already loaded when it forked are fixed for the life of the server: PHP
 * cannot redeclare a class a forked worker already holds, which is why every reloader excludes them
 * from its watch set at boot. Excluding them is right - reloading will not apply them - but it also
 * means editing one does nothing at all, with nothing said about why.
 *
 * ## Why this is not covered by ContainerFreshness
 *
 * Most of those classes are in the container's metadata, so it looks as though the container check
 * already covers them. It does not, from inside a server. Those entries are ReflectionClassResource,
 * and it decides freshness by reflecting the class and hashing the result - against the definition
 * loaded in the process doing the asking. A worker holds the old definition and will hold it until it
 * dies, so the hash it computes is the old one no matter what the file on disk now says, and the
 * resource reports fresh forever. In this bundle's own fixture that is 164 of the container's 234
 * resources; what the container check really catches from a worker are the config files, which are
 * plain FileResources compared by mtime.
 *
 * So this reads mtimes and sizes itself, and never reflects anything.
 *
 * ## Why the content is hashed
 *
 * An mtime alone answers "was this file written", not "did it change", and git checkouts, editors and
 * build steps all rewrite files byte for byte. Every one of those would ask for a restart nobody needs,
 * and a warning that cries wolf gets filtered out by the third time. So mtime and size are the cheap
 * gate, and the hash - taken once at boot, and recomputed only for a file whose gate moved - is what
 * actually decides.
 */
final class NonReloadableCodeFreshness implements Bootable, RestartCondition
{
    /**
     * @var array<string, array{mtime: int, size: int, hash: string}> path => what it looked like when
     *                                                                the workers forked
     */
    private array $baseline = [];

    private bool $captured = false;

    public function __construct(private readonly string $kernelCacheDir) {}

    /**
     * {@inheritDoc}
     *
     * Runs in the master, which is the only place the answer is right: this has to be the set of files
     * loaded before the fork, and a worker asking for its own would include whatever it has loaded
     * since - and would re-baseline against a file that had already changed after a reload.
     */
    #[Override]
    public function boot(array $runtimeConfiguration = []): void
    {
        $this->baseline = [];

        foreach (self::worthWatching(get_included_files(), $this->kernelCacheDir) as $path) {
            $state = self::stateOf($path);

            if ($state === null) {
                continue;
            }

            $this->baseline[$path] = $state;
        }

        $this->captured = true;
    }

    #[Override]
    public function reasonForRestart(): ?string
    {
        $changed = $this->changedFile();

        if ($changed === null) {
            return null;
        }

        return sprintf(
            '"%s" was loaded before the workers forked and has changed since, and PHP cannot redeclare '
            . 'what a forked worker already holds',
            $changed,
        );
    }

    /**
     * Whether there is a baseline to compare against at all.
     */
    public function canTell(): bool
    {
        return $this->captured && $this->baseline !== [];
    }

    /**
     * How many files are being watched, for anyone deciding whether that is a sensible number.
     */
    public function watchedFileCount(): int
    {
        return count($this->baseline);
    }

    private function changedFile(): ?string
    {
        if (!$this->canTell()) {
            return null;
        }

        // Asked from a worker that has been up for hours, and every answer below comes from a stat.
        // PHP caches those per process, so without this the check keeps reporting what the worker
        // happened to see the first time it looked.
        clearstatcache();

        foreach ($this->baseline as $path => $wasAtBoot) {
            $now = self::stateOf($path);

            if ($now === null) {
                // Gone, which no reload can undo either.
                return $path;
            }

            if ($now['mtime'] === $wasAtBoot['mtime'] && $now['size'] === $wasAtBoot['size']) {
                continue;
            }

            if ($now['hash'] === $wasAtBoot['hash']) {
                continue;
            }

            return $path;
        }

        return null;
    }

    /**
     * @return array{mtime: int, size: int, hash: string}|null
     */
    private static function stateOf(string $path): ?array
    {
        $mtime = @filemtime($path);
        $size = @filesize($path);
        $hash = @hash_file('xxh128', $path);

        if ($mtime === false || $size === false || $hash === false) {
            return null;
        }

        return ['mtime' => $mtime, 'size' => $size, 'hash' => $hash];
    }

    /**
     * The application's own share of what was loaded, which is the part a developer edits.
     *
     * Vendor and the cache directory are dropped, and that is most of it - 1317 of 1564 files in this
     * bundle's fixture. Neither is edited in place during development, and carrying them would turn a
     * check that costs nothing into one that walks thousands of files every couple of seconds.
     *
     * @param list<string> $included
     * @return list<string>
     */
    private static function worthWatching(array $included, string $kernelCacheDir): array
    {
        return array_values(array_filter(
            $included,
            static fn(string $path): bool => !str_contains($path, '/vendor/')
                && !str_starts_with($path, $kernelCacheDir),
        ));
    }
}
